<?php

declare(strict_types=1);

/** @noinspection PhpUndefinedClassInspection */
/** @noinspection PhpFullyQualifiedNameUsageInspection */
/** @noinspection PhpUnused */

namespace Illuminate\Database\Schema {
    /**
     * @method void hybridFullText(array<int, string> $columns, ?string $indexName = null) Create a portable full-text index (PostgreSQL/MySQL native, SQLite FTS5, or instructions for SQL Server).
     * @method void dropHybridFullText(string|array<int, string>|null $indexName = null) Drop a portable full-text index and its associated triggers/tables on SQLite.
     */
    class Blueprint {}
}

namespace Illuminate\Database\Query {
    /**
     * @method $this whereHybridFullText(string|array<int, string> $columns, string $value, array<string, mixed> $options = [], string $boolean = 'and', bool $not = false) Perform a portable full-text search. Supports PostgreSQL, MySQL, SQLite, and SQL Server (CONTAINS).
     */
    class Builder {}
}

namespace Illuminate\Database\Eloquent {
    /**
     * @method $this whereHybridFullText(string|array<int, string> $columns, string $value, array<string, mixed> $options = [], string $boolean = 'and', bool $not = false) Perform a portable full-text search.
     */
    class Builder {}
}
