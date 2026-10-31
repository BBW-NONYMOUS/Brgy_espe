<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Process;

/**
 * Uses mysqldump (XAMPP: C:\xampp\mysql\bin\mysqldump.exe — set
 * BACKUP_MYSQLDUMP_PATH). The password is passed through the MYSQL_PWD
 * environment variable so it never appears in the process list.
 */
class MysqlDumper implements DatabaseDumper
{
    public function dumpTo(string $path): void
    {
        $connection = config('database.default');
        $db = config("database.connections.{$connection}");

        $result = Process::timeout(600)
            ->env(['MYSQL_PWD' => (string) ($db['password'] ?? '')])
            ->run([
                config('barangay.backup.mysqldump_path'),
                '--single-transaction',
                '--quick',
                '--routines',
                '--triggers',
                '--default-character-set=utf8mb4',
                '--host='.$db['host'],
                '--port='.$db['port'],
                '--user='.$db['username'],
                '--result-file='.$path,
                $db['database'],
            ]);

        if ($result->failed()) {
            throw new BackupFailedException(trim($result->errorOutput()) ?: 'mysqldump exited with code '.$result->exitCode());
        }
    }
}
