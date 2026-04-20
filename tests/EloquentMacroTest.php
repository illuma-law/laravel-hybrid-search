<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class TestModel extends Model
{
    protected $table = 'test_table_eloquent';
}

it('calls whereHybridFullText on eloquent builder', function () {
    Config::set('database.default', 'testing');

    Schema::create('test_table_eloquent', function ($table) {
        $table->id();
        $table->string('title');
    });

    $query = TestModel::whereHybridFullText(['title'], 'search query');

    expect($query)->toBeInstanceOf(Builder::class);
    $sql = strtolower($query->toSql());
    expect($sql)->toContain('test_table_eloquent_fts match ?');
});
