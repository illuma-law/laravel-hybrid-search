# Laravel Hybrid Search

[![Tests](https://github.com/illuma-law/laravel-hybrid-search/actions/workflows/run-tests.yml/badge.svg)](https://github.com/illuma-law/laravel-hybrid-search/actions)
[![Packagist License](https://img.shields.io/badge/Licence-MIT-blue)](http://choosealicense.com/licenses/mit/)
[![Latest Stable Version](https://img.shields.io/packagist/v/illuma-law/laravel-hybrid-search?label=Version)](https://packagist.org/packages/illuma-law/laravel-hybrid-search)

Portable Full-Text Search macros and Reciprocal Rank Fusion for Laravel.

This package provides portable Full-Text Search (FTS) schema macros and the **Reciprocal Rank Fusion (RRF)** algorithm for Laravel applications. It enables seamless text searching across PostgreSQL, MySQL, SQL Server, and SQLite, abstracting away the database-specific syntax. It also provides an elegant way to merge and re-rank traditional keyword search results with AI vector search results.

## Features

- **Database Portability:** Write one migration and one query that works across all supported databases.
- **SQLite FTS5 Support:** Automatically creates Virtual Tables and database triggers to keep SQLite full-text indexes synchronized.
- **Reciprocal Rank Fusion:** Mathematically combine multiple ranked lists (e.g., BM25 + Vector Similarity) into a single optimized result set.
- **Scout Key Trait:** Ensure Scout-indexed models always return a string primary key — required for Typesense and other engines that reject integer keys.
- **Scout Health Check:** Optional `spatie/laravel-health` check that probes Meilisearch, Typesense, and Algolia endpoints.

## Database Support Matrix

| Database | Schema Macro | Query Builder Macro | Underlying Syntax |
| :--- | :--- | :--- | :--- |
| **PostgreSQL** | Native | Native | `whereFullText` |
| **MySQL** | Native | Native | `whereFullText` |
| **SQL Server** | Manual Instructions* | Native | `CONTAINS` |
| **SQLite** | Virtual Table + Triggers | Native | `MATCH` |

*\*SQL Server requires manual creation of the Full-Text Catalog.*

## Installation

You can install the package via composer:

```bash
composer require illuma-law/laravel-hybrid-search
```

The service provider will automatically register the `Blueprint` and `Builder` macros.

## Usage & Integration

### Schema Migrations

Use the `hybridFullText` macro in your migrations. 

On PostgreSQL and MySQL, this directly uses Laravel's native full-text index generation. On SQLite, it creates an `FTS5` virtual table (e.g., `articles_fts`) and sets up `INSERT`, `UPDATE`, and `DELETE` database triggers. This ensures your SQLite virtual table automatically stays synchronized with your main table without requiring any PHP-side application logic.

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->timestamps();
        });

        // Creates native FTS index on pg/mysql, or FTS5 virtual table + triggers on sqlite
        Schema::table('articles', function (Blueprint $table) {
            $table->hybridFullText(['title', 'body'], 'articles_search_index');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            // Safely drops native indexes or SQLite virtual tables/triggers
            $table->dropHybridFullText('articles_search_index');
        });
        
        Schema::dropIfExists('articles');
    }
};
```

### Full-Text Searching

Use the `whereHybridFullText` macro on any Query Builder or Eloquent Builder. It automatically handles the complex `MATCH` syntax for SQLite FTS5 and the `CONTAINS` syntax for SQL Server, while using native `whereFullText` on PostgreSQL and MySQL.

```php
use App\Models\Article;

// Search for articles containing "laravel macros"
$results = Article::query()
    ->whereHybridFullText(['title', 'body'], 'laravel macros')
    ->get();
```

You can also invert the search to exclude matches:

```php
// Find articles that DO NOT contain the word "outdated"
$results = Article::query()
    ->whereHybridFullText(['title', 'body'], 'outdated', not: true)
    ->get();
```

### Reciprocal Rank Fusion (RRF)

When building advanced search systems, you often want to retrieve the top results using traditional keyword search (BM25) and semantic vector search (Cosine Similarity), then combine them.

The `ReciprocalRankFusion` class merges these disparate result sets by assigning an RRF score to each item based on its position in the original ranked lists.

```php
use IllumaLaw\HybridSearch\ReciprocalRankFusion;
use App\Models\Article;

// 1. Get the top 50 IDs from Keyword Search
$keywordIds = Article::query()
    ->whereHybridFullText(['title', 'body'], 'authentication')
    ->limit(50)
    ->pluck('id');

// 2. Get the top 50 IDs from Vector Search
$vectorIds = Article::query()
    ->orderByVectorSimilarity('embedding', $queryVector) // Example syntax
    ->limit(50)
    ->pluck('id');

// 3. Combine and re-rank the IDs using RRF
$rankedScores = ReciprocalRankFusion::combine(
    [
        'keyword' => $keywordIds, 
        'vector' => $vectorIds
    ],
    k: 60 // The RRF constant (default is 60)
);

// $rankedScores is a Collection of [id => score], sorted descending by score.
$topIds = $rankedScores->keys();

// 4. Fetch the final ordered models
$finalResults = Article::whereIn('id', $topIds)
    ->orderByRaw('FIELD(id, ' . $topIds->implode(',') . ')')
    ->get();
```

## Scout Key Trait

Some search engines (e.g. Typesense) require Scout keys to be strings. Add the `EnsuresScoutKeyIsString` trait to any model that uses integer or UUID primary keys alongside the Scout `Searchable` trait.

```php
use IllumaLaw\HybridSearch\Concerns\EnsuresScoutKeyIsString;
use Laravel\Scout\Searchable;

class Article extends Model
{
    use EnsuresScoutKeyIsString, Searchable {
        EnsuresScoutKeyIsString::getScoutKey insteadof Searchable;
        EnsuresScoutKeyIsString::getScoutKeyName insteadof Searchable;
    }
}
```

## Scout Health Check

An optional `spatie/laravel-health` check that pings the configured Scout engine's health endpoint. Supports Meilisearch, Typesense, and Algolia. Non-remote drivers (`database`, `collection`, `null`) are automatically skipped.

Install the optional dependency first:

```bash
composer require spatie/laravel-health
```

Then register the check in your application:

```php
use IllumaLaw\HybridSearch\HealthChecks\ScoutEngineCheck;
use Spatie\Health\Facades\Health;

Health::checks([
    ScoutEngineCheck::new(),
]);
```

The check reads standard Scout configuration keys (`scout.driver`, `scout.meilisearch.*`, `scout.typesense.*`, `scout.algolia.*`). You can adjust the request timeout via `config('health.scout.timeout_seconds')` (default: 5).

## Testing

Run the test suite:

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
