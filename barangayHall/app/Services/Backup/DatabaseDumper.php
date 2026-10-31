<?php

namespace App\Services\Backup;

interface DatabaseDumper
{
    /**
     * Write a full SQL dump of the default connection to $path.
     *
     * @throws BackupFailedException
     */
    public function dumpTo(string $path): void;
}
