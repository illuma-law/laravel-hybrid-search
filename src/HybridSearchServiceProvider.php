<?php

declare(strict_types=1);

namespace IllumaLaw\HybridSearch;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class HybridSearchServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-hybrid-search');
    }

    public function bootingPackage(): void
    {
        Blueprint::macro('hybridFullText', function (array $columns, ?string $indexName = null): void {
            if (! $this instanceof Blueprint) {
                return;
            }
            /** @phpstan-ignore-next-line */
            $table = $this->table;

            if (config('database.default') === 'sqlsrv') {
                throw new \RuntimeException('SQL Server requires manual Full-Text Index creation. Please create a Full-Text Catalog and then a Full-Text Index on table "'.$table.'" for columns: '.implode(', ', $columns));
            }

            FullTextSchema::index($table, $columns, $indexName);
        });

        Blueprint::macro('dropHybridFullText', function (string|array|null $indexName = null): void {
            /** @var Blueprint $self */
            $self = $this;
            /** @phpstan-ignore-next-line */
            $table = $self->table;

            FullTextSchema::drop($table, $indexName);
        });

        $this->registerWhereHybridFullTextMacro();
    }

    private function registerWhereHybridFullTextMacro(): void
    {
        Builder::macro('whereHybridFullText', function ($columns, $value, array $options = [], string $boolean = 'and', bool $not = false): Builder {
            /** @var Builder $self */
            $self = $this;
            $driver = $self->getConnection()->getDriverName();

            // 1. SQLite Path
            if ($driver === 'sqlite') {
                $table = $self->from;
                $ftsTable = "{$table}_fts";
                $escapedValue = '"'.str_replace(['"', '\\'], ['""', '\\\\'], $value).'"';
                $operator = $not ? 'NOT IN' : 'IN';
                $raw = "{$self->getGrammar()->wrap($table)}.{$self->getGrammar()->wrap('id')} {$operator} (SELECT rowid FROM {$ftsTable} WHERE {$ftsTable} MATCH ?)";

                return $self->whereRaw($raw, [$escapedValue], $boolean);
            }

            // 2. SQL Server Path (Laravel doesn't support whereFullText for sqlsrv)
            if ($driver === 'sqlsrv') {
                $columns = is_array($columns) ? implode(', ', array_map([$self->getGrammar(), 'wrap'], $columns)) : $self->getGrammar()->wrap($columns);
                $placeholder = $self->getGrammar()->parameter($value);
                $raw = sprintf('%sCONTAINS((%s), %s)', $not ? 'NOT ' : '', $columns, $placeholder);

                return $self->whereRaw($raw, [$value], $boolean);
            }

            // 3. PostgreSQL / MySQL Path (Use native whereFullText)
            if ($not) {
                /** @phpstan-ignore-next-line */
                return $self->whereNot(function ($query) use ($columns, $value, $options) {
                    $query->whereFullText($columns, $value, $options);
                }, $boolean);
            }

            /** @phpstan-ignore-next-line */
            return $self->whereFullText($columns, $value, $options, $boolean);
        });

        \Illuminate\Database\Eloquent\Builder::macro('whereHybridFullText', function ($columns, $value, array $options = [], string $boolean = 'and', bool $not = false) {
            /** @var \Illuminate\Database\Eloquent\Builder $self */
            $self = $this;
            $self->getQuery()->whereHybridFullText($columns, $value, $options, $boolean, $not);

            return $self;
        });
    }
}
