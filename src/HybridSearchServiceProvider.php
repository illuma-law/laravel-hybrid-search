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
        /** @var mixed $blueprintClass */
        $blueprintClass = Blueprint::class;
        $blueprintClass::macro('hybridFullText', function (array $columns, ?string $indexName = null): void {
            /** @var mixed $self */
            $self = $this;

            if (! $self instanceof Blueprint) {
                return;
            }

            /** @var string $table */
            $table = (new \ReflectionClass($self))->getProperty('table')->getValue($self);

            if (config('database.default') === 'sqlsrv') {
                /** @var array<int, string> $columns */
                throw new \RuntimeException('SQL Server requires manual Full-Text Index creation. Please create a Full-Text Catalog and then a Full-Text Index on table "'.$table.'" for columns: '.implode(', ', $columns));
            }

            /** @var array<int, string> $columns */
            FullTextSchema::index($table, $columns, $indexName);
        });

        $blueprintClass::macro('dropHybridFullText', function (string|array|null $indexName = null): void {
            /** @var mixed $self */
            $self = $this;

            if (! $self instanceof Blueprint) {
                return;
            }

            /** @var string $table */
            $table = (new \ReflectionClass($self))->getProperty('table')->getValue($self);

            /** @var string|array<int, string>|null $indexName */
            FullTextSchema::drop($table, $indexName);
        });

        $this->registerWhereHybridFullTextMacro();
    }

    private function registerWhereHybridFullTextMacro(): void
    {
        /** @var mixed $builderClass */
        $builderClass = Builder::class;
        $builderClass::macro('whereHybridFullText', function (string|array $columns, string $value, array $options = [], string $boolean = 'and', bool $not = false): Builder {
            /** @var mixed $self */
            $self = $this;

            if (! $self instanceof Builder) {
                throw new \RuntimeException('Expected Builder');
            }

            /** @var mixed $connection */
            $connection = $self->getConnection();
            /** @var string $driver */
            $driver = $connection->getDriverName();

            if ($driver === 'sqlite') {
                /** @var mixed $from */
                $from = $self->from;
                /** @var string $table */
                $table = (string) $from;
                $ftsTable = "{$table}_fts";
                $escapedValue = '"'.str_replace(['"', '\\'], ['""', '\\\\'], $value).'"';
                $operator = $not ? 'NOT IN' : 'IN';

                /** @var mixed $grammar */
                $grammar = $self->getGrammar();
                $wrappedTable = (string) $grammar->wrap($table);
                $wrappedId = (string) $grammar->wrap('id');

                $raw = "{$wrappedTable}.{$wrappedId} {$operator} (SELECT rowid FROM {$ftsTable} WHERE {$ftsTable} MATCH ?)";

                /** @var Builder $res */
                $res = $self->whereRaw($raw, [$escapedValue], $boolean);

                return $res;
            }

            if ($driver === 'sqlsrv') {
                /** @var mixed $grammar */
                $grammar = $self->getGrammar();
                $columnsList = is_array($columns) ? implode(', ', array_map(fn (mixed $col) => (string) $grammar->wrap(is_string($col) ? $col : ''), $columns)) : (string) $grammar->wrap($columns);
                $placeholder = (string) $grammar->parameter($value);
                $raw = sprintf('%sCONTAINS((%s), %s)', $not ? 'NOT ' : '', $columnsList, $placeholder);

                /** @var Builder $res */
                $res = $self->whereRaw($raw, [$value], $boolean);

                return $res;
            }

            if ($not) {
                /** @var Builder $res */
                $res = $self->whereNot(function (Builder $query) use ($columns, $value, $options) {
                    /** @var string|array<int, string> $columns */
                    return $query->whereFullText($columns, $value, $options);
                }, $boolean);

                return $res;
            }

            /** @var string|array<int, string> $columns */
            /** @var Builder $res */
            $res = $self->whereFullText($columns, $value, $options, $boolean);

            return $res;
        });

        /** @var mixed $eloquentBuilderClass */
        $eloquentBuilderClass = EloquentBuilder::class;
        $eloquentBuilderClass::macro('whereHybridFullText', function (string|array $columns, string $value, array $options = [], string $boolean = 'and', bool $not = false): EloquentBuilder {
            /** @var mixed $self */
            $self = $this;

            if (! $self instanceof EloquentBuilder) {
                throw new \RuntimeException('Expected EloquentBuilder');
            }

            /** @var mixed $query */
            $query = $self->getQuery();
            $query->whereHybridFullText($columns, $value, $options, $boolean, $not);

            return $self;
        });
    }
}
