<?php

declare(strict_types=1);

use IllumaLaw\HybridSearch\FullTextSchema;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\MySqlGrammar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('registers the hybridFullText macro on blueprint', function () {
    expect(Blueprint::hasMacro('hybridFullText'))->toBeTrue();
});

it('can create a fulltext index and virtual table for sqlite', function () {
    Schema::create('test_fts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->text('body');
    });

    Schema::table('test_fts', function (Blueprint $table) {
        $table->hybridFullText(['title', 'body']);
    });

    $exists = DB::selectOne("SELECT name FROM sqlite_master WHERE type='table' AND name='test_fts_fts'");
    expect($exists)->not->toBeNull();

    $triggers = DB::select("SELECT name FROM sqlite_master WHERE type='trigger' AND name LIKE 'test_fts_fts_%'");
    expect(count($triggers))->toBe(3);

    Schema::table('test_fts', function (Blueprint $table) {
        $table->dropHybridFullText();
    });

    $existsAfter = DB::selectOne("SELECT name FROM sqlite_master WHERE type='table' AND name='test_fts_fts'");
    expect($existsAfter)->toBeNull();
});

it('can drop native fulltext index', function () {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->andReturn('mysql');
    $connection->shouldReceive('getSchemaGrammar')->atLeast()->once()->andReturn(new MySqlGrammar($connection));
    $connection->shouldReceive('getConfig')->with('prefix_indexes')->andReturn(false);

    Schema::shouldReceive('getConnection')->andReturn($connection);
    Schema::shouldReceive('table')->with('test_table', Mockery::type('Closure'))->once()
        ->andReturnUsing(function ($table, $callback) use ($connection) {
            $callback(new Blueprint($connection, $table));
        });

    FullTextSchema::drop('test_table', ['title']);
    expect(true)->toBeTrue();
});
