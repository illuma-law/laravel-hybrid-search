<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class TestModel extends Model
{
    protected $table = 'test_table_eloquent';
}

it('calls whereHybridFullText on eloquent builder', function () {
    Config::set('database.default', 'testing');

    Schema::create('test_table_eloquent', function (Blueprint $table) {
        $table->id();
        $table->string('title');
    });

    /** @var Builder<TestModel> $query */
    $query = TestModel::whereHybridFullText(['title'], 'search query');

    expect($query)->not->toBeNull();
    /** @var mixed $baseQuery */
    $baseQuery = $query->getQuery();
    if (method_exists($baseQuery, 'toSql')) {
        $sql = strtolower((string) $baseQuery->toSql());
        expect($sql)->toContain('test_table_eloquent_fts match ?');
    }
});
