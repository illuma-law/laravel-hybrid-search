<?php

declare(strict_types=1);

namespace IllumaLaw\HybridSearch;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
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
            /** @var Blueprint $self */
            $self = $this;

            /** @var string $table */
            $table = (new \ReflectionClass($self))->getProperty('table')->getValue($self);

            if (config('database.default') === 'sqlsrv') {
                /** @var array<int, string> $columns */
                throw new \RuntimeException('SQL Server requires manual Full-Text Index creation. Please create a Full-Text Catalog and then a Full-Text Index on table "'.$table.'" for columns: '.implode(', ', $columns));
            }

            /** @var array<int, string> $columns */
            FullTextSchema::index($table, $columns, $indexName);
        });

        Blueprint::macro('dropHybridFullText', function (string|array|null $indexName = null): void {
            /** @var Blueprint $self */
            $self = $this;

            /** @var string $table */
            $table = (new \ReflectionClass($self))->getProperty('table')->getValue($self);

            /** @var string|array<int, string>|null $indexName */
            FullTextSchema::drop($table, $indexName);
        });

        $this->registerWhereHybridFullTextMacro();
    }

    private function registerWhereHybridFullTextMacro(): void
    {
        Builder::macro('whereHybridFullText', function (string|array $columns, string $value, array $options = [], string $boolean = 'and', bool $not = false): Builder {
            /** @var Builder $self */
            $self = $this;

            /** @var \Illuminate\Database\Connection $connection */
            $connection = $self->getConnection();
            $driver = $connection->getDriverName();

            if ($driver === 'sqlite') {
                /** @var string $table */
                $table = $self->from;
                $ftsTable = "{$table}_fts";
                $escapedValue = '"'.str_replace(['"', '\\'], ['""', '\\\\'], $value).'"';
                $operator = $not ? 'NOT IN' : 'IN';

                /** @var \Illuminate\Database\Query\Grammars\Grammar $grammar */
                $grammar = $self->getGrammar();
                $wrappedTable = (string) $grammar->wrap($table);
                $wrappedId = (string) $grammar->wrap('id');

                $raw = "{$wrappedTable}.{$wrappedId} {$operator} (SELECT rowid FROM {$ftsTable} WHERE {$ftsTable} MATCH ?)";

                /** @phpstan-ignore-next-line */
                return $self->whereRaw($raw, [$escapedValue], $boolean);
            }

            if ($driver === 'sqlsrv') {
                /** @var \Illuminate\Database\Query\Grammars\Grammar $grammar */
                $grammar = $self->getGrammar();
                $columnsList = is_array($columns) ? implode(', ', array_map(fn (mixed $col) => (string) $grammar->wrap(is_string($col) ? $col : ''), $columns)) : (string) $grammar->wrap($columns);
                $placeholder = (string) $grammar->parameter($value);
                $raw = sprintf('%sCONTAINS((%s), %s)', $not ? 'NOT ' : '', $columnsList, $placeholder);

                /** @phpstan-ignore-next-line */
                return $self->whereRaw($raw, [$value], $boolean);
            }

            if ($not) {
                /** @phpstan-ignore-next-line */
                return $self->whereNot(function (Builder $query) use ($columns, $value, $options) {
                    /** @var string|array<int, string> $columns */
                    return $query->whereFullText($columns, $value, $options);
                }, $boolean);
            }

            /** @var string|array<int, string> $columns */
            /** @phpstan-ignore-next-line */
            return $self->whereFullText($columns, $value, $options, $boolean);
        });

        EloquentBuilder::macro('whereHybridFullText', function (string|array $columns, string $value, array $options = [], string $boolean = 'and', bool $not = false): EloquentBuilder {
            /** @var EloquentBuilder<\Illuminate\Database\Eloquent\Model> $self */
            $self = $this;

            /** @phpstan-ignore-next-line */
            $self->getQuery()->whereHybridFullText($columns, $value, $options, $boolean, $not);

            return $self;
        });
    }
}
