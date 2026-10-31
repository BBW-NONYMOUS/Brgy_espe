<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;

/**
 * Development fallback: copies the SQLite database file.
 */
class SqliteDumper implements DatabaseDumper
{
    public function dumpTo(string $path): void
    {
        $database = (string) DB::connection()->getConfig('database');

        if ($database === ':memory:' || ! is_file($database)) {
            throw new BackupFailedException('The SQLite database is not stored in a file.');
        }

        if (! copy($database, $path)) {
            throw new BackupFailedException("Could not copy {$database}.");
        }
    }
}
