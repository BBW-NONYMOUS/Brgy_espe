<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * DDL that only MySQL / MariaDB understand. The test suite runs on SQLite,
 * where these statements are skipped; validation rules enforce the same
 * limits at the application layer.
 */
class MysqlSchema
{
    public static function supported(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    public static function check(string $table, string $name, string $expression): void
    {
        if (self::supported()) {
            DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` CHECK ({$expression})");
        }
    }

    /**
     * @param  list<string>  $columns
     */
    public static function fullText(string $table, string $name, array $columns): void
    {
        if (self::supported()) {
            $list = implode(', ', array_map(fn (string $column) => "`{$column}`", $columns));

            DB::statement("ALTER TABLE `{$table}` ADD FULLTEXT INDEX `{$name}` ({$list})");
        }
    }
}
