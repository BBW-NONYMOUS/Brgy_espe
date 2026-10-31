<?php

namespace App\Services\Backup;

use App\Enums\AuditAction;
use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Models\Backup;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Creates encrypted, compressed database backups and rotates old ones.
 *
 * File format: Crypt::encryptString(gzencode(sql)) — AES-256-CBC with the
 * APP_KEY. Without the APP_KEY a backup cannot be restored, so keep a copy
 * of the .env file somewhere safe (see docs/restore.md).
 */
class BackupService
{
    public function __construct(
        private readonly DatabaseDumper $dumper,
        private readonly AuditLogger $audit,
    ) {}

    public function run(BackupType $type, ?User $user = null): Backup
    {
        $fileName = sprintf('backup-%s-%s.sql.gz.enc', now()->format('Y-m-d_His'), $type->value);

        $backup = Backup::query()->create([
            'file_name' => $fileName,
            'type' => $type,
            'status' => BackupStatus::Running,
            'created_by' => $user?->id,
        ]);

        $tmp = tempnam(sys_get_temp_dir(), 'bhdump');

        try {
            if ($tmp === false) {
                throw new BackupFailedException('Could not create a temporary file.');
            }

            $this->dumper->dumpTo($tmp);

            $sql = (string) file_get_contents($tmp);
            $compressed = gzencode($sql, 9);

            if ($sql === '' || $compressed === false) {
                throw new BackupFailedException('The database dump was empty.');
            }

            $this->disk()->put($this->path($fileName), Crypt::encryptString($compressed));

            $backup->update([
                'status' => BackupStatus::Success,
                'size_bytes' => $this->disk()->size($this->path($fileName)),
                'completed_at' => now(),
            ]);

            $this->audit->log(AuditAction::Backup, $backup, "{$type->label()} backup created: {$fileName}", user: $user);
        } catch (Throwable $e) {
            $backup->update(['status' => BackupStatus::Failed, 'error' => $e->getMessage(), 'completed_at' => now()]);
            $this->audit->log(AuditAction::Backup, $backup, "{$type->label()} backup FAILED: {$e->getMessage()}", user: $user);
        } finally {
            if (is_string($tmp) && is_file($tmp)) {
                @unlink($tmp);
            }
        }

        return $backup->refresh();
    }

    /**
     * Decrypt and decompress a backup back into plain SQL.
     */
    public function decrypt(string $encrypted): string
    {
        $sql = gzdecode(Crypt::decryptString($encrypted));

        if ($sql === false) {
            throw new BackupFailedException('The backup could not be decompressed.');
        }

        return $sql;
    }

    public function contents(Backup $backup): string
    {
        return (string) $this->disk()->get($this->path($backup->file_name));
    }

    public function exists(Backup $backup): bool
    {
        return $backup->status === BackupStatus::Success && $this->disk()->exists($this->path($backup->file_name));
    }

    /**
     * Keep the last N daily scheduled backups, one per week for N weeks
     * (Sundays), and the last N manual backups. Older files are deleted;
     * their rows stay as history with status "pruned".
     *
     * @return int Number of backups pruned
     */
    public function prune(): int
    {
        $config = config('barangay.backup');
        $dailyCutoff = now()->subDays($config['keep_daily']);
        $weeklyCutoff = now()->subWeeks($config['keep_weekly']);

        $successful = Backup::query()->where('status', BackupStatus::Success)->latest('created_at')->latest('id')->get();

        $expiredScheduled = $successful
            ->where('type', BackupType::Scheduled)
            ->filter(fn (Backup $b) => $b->created_at->lt($dailyCutoff)
                && ! ($b->created_at->isSunday() && $b->created_at->gte($weeklyCutoff)));

        $expiredManual = $successful->where('type', BackupType::Manual)->slice($config['keep_manual']);

        $expired = $expiredScheduled->merge($expiredManual);

        foreach ($expired as $backup) {
            $this->disk()->delete($this->path($backup->file_name));
            $backup->update(['status' => BackupStatus::Pruned]);
        }

        if ($expired->isNotEmpty()) {
            $this->audit->log(AuditAction::Backup, null, "Backup rotation removed {$expired->count()} old backup file(s)");
        }

        return $expired->count();
    }

    public function path(string $fileName): string
    {
        return config('barangay.backup.directory').'/'.$fileName;
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('barangay.backup.disk'));
    }
}
