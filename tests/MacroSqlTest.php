<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Database\Query\Grammars\SqlServerGrammar;
use Illuminate\Database\Query\Processors\Processor;
use Mockery\MockInterface;

function getHybridSearchMockBuilder(string $driver): Builder
{
    /** @var Connection&MockInterface $connection */
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('raw')->andReturnUsing(fn (mixed $value): Expression => new Expression((string) $value));

    $grammar = match ($driver) {
        'mysql'       => new MySqlGrammar($connection),
        'pgsql'       => new PostgresGrammar($connection),
        'sqlite'      => new SQLiteGrammar($connection),
        'sqlsrv'      => new SqlServerGrammar($connection),
        'mariadb'     => new MySqlGrammar($connection),
        'singlestore' => new MySqlGrammar($connection),
        default       => throw new InvalidArgumentException("Unsupported driver: {$driver}"),
    };

    $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
    $connection->shouldReceive('getPostProcessor')->andReturn(new Processor);
    $connection->shouldReceive('getDatabaseName')->andReturn('laravel');
    $connection->shouldReceive('getDriverName')->andReturn($driver);

    return new Builder($connection, $grammar);
}

it('generates correct hybrid fulltext SQL for each driver', function (string $driver) {
    $builder = getHybridSearchMockBuilder($driver);
    $builder->from('test_table');

    if ($driver === 'sqlite') {
        $builder->whereHybridFullText(['title', 'body'], 'search query');
        $sql = strtolower($builder->toSql());
        expect($sql)->toContain('test_table_fts match ?');
    } elseif ($driver === 'sqlsrv') {
        $builder->whereHybridFullText(['title', 'body'], 'search query');
        $sql = strtolower($builder->toSql());
        expect($sql)->toContain('contains(([title], [body]), ?)');
    } else {
        $builder->whereHybridFullText(['title', 'body'], 'search query');
        $sql = strtolower($builder->toSql());

        if ($driver === 'pgsql') {
            expect($sql)->toContain('to_tsvector');
        } else {
            expect($sql)->toContain('match (`title`, `body`) against (? in natural language mode)');
        }
    }
})->with(['pgsql', 'mysql', 'sqlite', 'sqlsrv', 'mariadb', 'singlestore']);

it('handles negative hybrid fulltext search', function (string $driver) {
    $builder = getHybridSearchMockBuilder($driver);
    $builder->from('test_table');

    $builder->whereHybridFullText(['title'], 'query', [], 'and', true);
    $sql = strtolower($builder->toSql());

    if ($driver === 'sqlite') {
        expect($sql)->toContain('not in');
    } elseif ($driver === 'sqlsrv') {
        expect($sql)->toContain('not contains');
    } else {
        expect($sql)->toMatch('/not\s*\(|!\s*\(/i');
    }
})->with(['pgsql', 'mysql', 'sqlite', 'sqlsrv', 'mariadb', 'singlestore']);
