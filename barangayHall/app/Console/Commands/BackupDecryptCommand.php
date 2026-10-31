<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Turns an encrypted backup (*.sql.gz.enc) back into a plain .sql file that
 * can be imported with the mysql client. See docs/restore.md.
 */
class BackupDecryptCommand extends Command
{
    protected $signature = 'backup:decrypt {file : Path to the .sql.gz.enc backup file} {--output= : Where to write the .sql file}';

    protected $description = 'Decrypt and decompress a database backup into a .sql file';

    public function handle(BackupService $backups): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file)) {
            $file = storage_path('app/private/'.$backups->path(basename($file)));
        }

        if (! is_file($file)) {
            $this->components->error("Backup file not found: {$this->argument('file')}");

            return self::FAILURE;
        }

        try {
            $sql = $backups->decrypt((string) file_get_contents($file));
        } catch (Throwable $e) {
            $this->components->error('Could not decrypt the backup. Is APP_KEY the same as when the backup was made? ('.$e->getMessage().')');

            return self::FAILURE;
        }

        $output = (string) ($this->option('output') ?: preg_replace('/\.gz\.enc$/', '', $file));
        file_put_contents($output, $sql);

        $this->components->info("Decrypted SQL written to {$output}");
        $this->components->warn('This file contains unencrypted personal health data. Delete it after restoring.');

        return self::SUCCESS;
    }
}
