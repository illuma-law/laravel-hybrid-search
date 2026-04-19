<?php

use Illuminate\Database\Schema\Blueprint;
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
});
