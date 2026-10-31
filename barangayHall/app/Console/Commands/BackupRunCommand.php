<?php

namespace App\Console\Commands;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Services\Backup\BackupService;
use Illuminate\Console\Command;

class BackupRunCommand extends Command
{
    protected $signature = 'backup:run {--manual : Record the backup as manual instead of scheduled} {--no-prune : Skip the retention rotation}';

    protected $description = 'Create an encrypted, compressed database backup and rotate old backups';

    public function handle(BackupService $backups): int
    {
        $backup = $backups->run($this->option('manual') ? BackupType::Manual : BackupType::Scheduled);

        if ($backup->status !== BackupStatus::Success) {
            $this->components->error("Backup failed: {$backup->error}");

            return self::FAILURE;
        }

        $this->components->info(sprintf('Backup created: %s (%s KB)', $backup->file_name, number_format(($backup->size_bytes ?? 0) / 1024, 1)));

        if (! $this->option('no-prune')) {
            $pruned = $backups->prune();
            $this->components->info("Rotation: {$pruned} old backup(s) removed.");
        }

        return self::SUCCESS;
    }
}
