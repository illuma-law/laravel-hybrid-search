<?php

declare(strict_types=1);

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\SqlServerGrammar;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

it('calls hybridFullText from macro on sqlite', function () {
    Config::set('database.default', 'testing');

    Schema::create('test_table_macro', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->text('body');
    });

    Schema::table('test_table_macro', function (Blueprint $table) {
        $table->hybridFullText(['title', 'body']);
    });

    expect(Schema::hasTable('test_table_macro_fts'))->toBeTrue();
});

it('calls dropHybridFullText from macro on sqlite', function () {
    Config::set('database.default', 'testing');

    Schema::create('test_table_drop', function (Blueprint $table) {
        $table->id();
        $table->string('title');
    });

    Schema::table('test_table_drop', function (Blueprint $table) {
        $table->hybridFullText(['title']);
        $table->dropHybridFullText();
    });

    expect(Schema::hasTable('test_table_drop_fts'))->toBeFalse();
});

it('throws exception for hybridFullText on sqlsrv', function () {
    Config::set('database.default', 'sqlsrv');
    Config::set('database.connections.sqlsrv.driver', 'sqlsrv');

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getSchemaGrammar')->andReturn(new SqlServerGrammar($connection));
    $connection->shouldReceive('getDriverName')->andReturn('sqlsrv');

    $blueprint = new Blueprint($connection, 'test_table');
    $blueprint->hybridFullText(['title']);
})->throws(RuntimeException::class, 'SQL Server requires manual Full-Text Index creation');
