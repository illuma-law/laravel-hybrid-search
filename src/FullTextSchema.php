<?php

declare(strict_types=1);

namespace IllumaLaw\HybridSearch;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class FullTextSchema
{
    public static function index(string $table, array $columns, ?string $indexName = null): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            self::createSqliteFts5($table, $columns);

            return;
        }

        self::createNative($table, $columns, $indexName);
    }

    public static function drop(string $table, string|array|null $indexName = null): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            self::dropSqliteFts5($table);

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
            $blueprint->dropFullText($indexName);
        });
    }

    private static function createNative(string $table, array $columns, ?string $indexName): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName): void {
            $blueprint->fullText($columns, $indexName);
        });
    }

    private static function createSqliteFts5(string $table, array $columns): void
    {
        $ftsTable = "{$table}_fts";
        $cols = implode(', ', $columns);

        self::dropSqliteFts5($table);

        DB::statement(sprintf(
            'CREATE VIRTUAL TABLE %s USING fts5(%s, content=%s, content_rowid=id)',
            $ftsTable,
            $cols,
            DB::getPdo()->quote($table)
        ));

        DB::statement(sprintf(
            'CREATE TRIGGER %s AFTER INSERT ON %s BEGIN
                INSERT INTO %s(rowid, %s) VALUES (new.id, %s);
            END',
            "{$table}_fts_insert",
            $table,
            $ftsTable,
            $cols,
            implode(', ', array_map(fn ($col) => "new.{$col}", $columns))
        ));

        DB::statement(sprintf(
            'CREATE TRIGGER %s AFTER UPDATE ON %s BEGIN
                DELETE FROM %s WHERE rowid = old.id;
                INSERT INTO %s(rowid, %s) VALUES (new.id, %s);
            END',
            "{$table}_fts_update",
            $table,
            $ftsTable,
            $ftsTable,
            $cols,
            implode(', ', array_map(fn ($col) => "new.{$col}", $columns))
        ));

        DB::statement(sprintf(
            'CREATE TRIGGER %s AFTER DELETE ON %s BEGIN
                DELETE FROM %s WHERE rowid = old.id;
            END',
            "{$table}_fts_delete",
            $table,
            $ftsTable
        ));

        DB::statement(sprintf(
            'INSERT INTO %s(rowid, %s) SELECT id, %s FROM %s',
            $ftsTable,
            $cols,
            $cols,
            $table
        ));
    }

    private static function dropSqliteFts5(string $table): void
    {
        $ftsTable = "{$table}_fts";

        DB::statement("DROP TRIGGER IF EXISTS {$table}_fts_insert");
        DB::statement("DROP TRIGGER IF EXISTS {$table}_fts_update");
        DB::statement("DROP TRIGGER IF EXISTS {$table}_fts_delete");
        DB::statement("DROP TABLE IF EXISTS {$ftsTable}");
    }
}
