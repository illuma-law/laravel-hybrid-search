# illuma-law/laravel-hybrid-search

Portable Full-Text Search (FTS) macros and Reciprocal Rank Fusion (RRF) for Laravel. Supports PostgreSQL, MySQL, SQL Server, and SQLite (FTS5).

## Schema Macros

Creates native FTS indexes (PG/MySQL) or FTS5 virtual tables + triggers (SQLite).

```php
Schema::table('articles', function (Blueprint $table) {
    $table->hybridFullText(['title', 'body'], 'search_index');
});

// To drop:
$table->dropHybridFullText('search_index');
```

## Querying

```php
$results = Article::query()
    ->whereHybridFullText(['title', 'body'], 'search query')
    ->get();
```

## Reciprocal Rank Fusion (RRF)

Merges and re-ranks multiple result sets (e.g., Keyword + Vector search).

```php
use IllumaLaw\HybridSearch\ReciprocalRankFusion;

$rankedScores = ReciprocalRankFusion::combine([
    'keyword' => $keywordIds,
    'vector' => $vectorIds
], k: 60);

$topIds = $rankedScores->keys();
```
