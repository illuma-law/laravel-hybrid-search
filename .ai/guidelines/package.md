---
description: Portable Full-Text Search macros and Reciprocal Rank Fusion for Laravel — cross-database, RRF merging
---

# laravel-hybrid-search

Portable Full-Text Search schema macros and Reciprocal Rank Fusion (RRF) for Laravel. Abstracts database-specific FTS syntax; merges keyword + vector search results.

## Namespace

`IllumaLaw\HybridSearch`

## Key Macros

- `Blueprint::hybridFullText(columns, name)` — schema macro for migrations
- `Builder::hybridFullTextSearch(columns, query)` — query builder macro
- `Builder::hybridRrfMerge(...)` — merges ranked lists with RRF

## Scout Health Check

`IllumaLaw\HybridSearch\HealthChecks\ScoutEngineCheck` — probes Meilisearch, Typesense, or Algolia.

## Schema Migration

```php
Schema::create('documents', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('body');
    $table->timestamps();

    $table->hybridFullText(['title', 'body'], 'documents_fts');
    // PostgreSQL/MySQL: native full-text index
    // SQLite: FTS5 virtual table + auto-sync triggers
});
```

## Query

```php
// Full-text keyword search
$results = Document::query()
    ->hybridFullTextSearch(['title', 'body'], $userQuery)
    ->limit(20)
    ->get();
```

## Reciprocal Rank Fusion

```php
use IllumaLaw\HybridSearch\Rrf;

// Merge keyword results and vector results
$merged = Rrf::merge(
    lists: [$keywordResults, $vectorResults],
    idKey: 'id',
    k: 60, // RRF constant
);
```

## Scout Key Trait

Ensures Scout-indexed models return a string primary key (required for Typesense):

```php
use IllumaLaw\HybridSearch\Traits\HasScoutStringKey;

class Document extends Model
{
    use HasScoutStringKey;
}
```

## Health Check Registration

```php
use IllumaLaw\HybridSearch\HealthChecks\ScoutEngineCheck;

Health::checks([ScoutEngineCheck::new()]);
```
