# Laravel Hybrid Search

[![Tests](https://github.com/illuma-law/laravel-hybrid-search/actions/workflows/run-tests.yml/badge.svg)](https://github.com/illuma-law/laravel-hybrid-search/actions)
[![Packagist License](https://img.shields.io/badge/Licence-MIT-blue)](http://choosealicense.com/licenses/mit/)
[![Latest Stable Version](https://img.shields.io/packagist/v/illuma-law/laravel-hybrid-search?label=Version)](https://packagist.org/packages/illuma-law/laravel-hybrid-search)

**Portable Full-Text Search macros and Reciprocal Rank Fusion**

This package provides portable Full-Text Search (FTS) schema macros and the **Reciprocal Rank Fusion (RRF)** algorithm for Laravel. It enables seamless text searching across PostgreSQL, MySQL, SQL Server, and SQLite (via FTS5 virtual tables), and provides an elegant way to combine keyword search results with vector search results.

- [Database Support Matrix](#database-support-matrix)
- [Installation](#installation)
- [Usage](#usage)
  - [Schema Migrations](#schema-migrations)
  - [Full-Text Searching](#full-text-searching)
  - [Reciprocal Rank Fusion (RRF)](#reciprocal-rank-fusion-rrf)
- [Testing](#testing)
- [Credits](#credits)
- [License](#license)

## Database Support Matrix

| Database | Full-Text Macro | Query Macro | Syntax |
| :--- | :--- | :--- | :--- |
| **PostgreSQL** | Native | Native | `whereFullText` |
| **MySQL** | Native | Native | `whereFullText` |
| **SQL Server** | Manual Instructions | Native (via `CONTAINS`) | `CONTAINS` |
| **SQLite** | Virtual Table + Triggers | Native (via MATCH) | `MATCH` |

## Installation

Require this package with composer using the following command:

```bash
composer require illuma-law/laravel-hybrid-search
```

## Usage

### TL;DR

Create a full-text index in your migration:
```php
$table->hybridFullText(['title', 'body'], 'articles_search_index');
```

Perform a keyword search:
```php
$results = Article::query()->whereHybridFullText(['title', 'body'], 'laravel macros')->get();
```

Combine keyword and vector search results:
```php
$combined = ReciprocalRankFusion::combine([$keywordResults, $vectorResults]);
```

### Schema Migrations

Use the `hybridFullText` macro to define portable full-text indexes. 

On PostgreSQL and MySQL, this delegates to Laravel's native full-text index generation. On SQLite, it intelligently creates an `FTS5` virtual table (e.g., `articles_fts`) and sets up `INSERT`, `UPDATE`, and `DELETE` database triggers to ensure the virtual table automatically stays synchronized with your main table.

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

        // Creates native FTS index on pgsql/mysql, 
        // or FTS5 virtual table + triggers on sqlite.
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

*Note for SQL Server: The macro will throw an exception with instructions to manually create the Full-Text Catalog and Index, as SQL Server requires specific configuration for FTS.*

### Full-Text Searching

Use the `whereHybridFullText` macro on any Query Builder or Eloquent Builder instance to perform text searches. It safely abstracts the complex `MATCH` syntax required by SQLite's FTS5, and the `CONTAINS` syntax for SQL Server, while using native `whereFullText` capabilities on PostgreSQL and MySQL.

```php
$results = Article::query()
    ->whereHybridFullText(['title', 'body'], 'laravel hybrid search')
    ->get();
```

You can also invert the search to exclude matches:

```php
$results = Article::query()
    ->whereHybridFullText(['title', 'body'], 'outdated', not: true)
    ->get();
```

### Reciprocal Rank Fusion (RRF)

When building advanced search systems, you often want to combine results from multiple retrieval strategies. For example, you might retrieve the top 50 results using traditional keyword search (BM25), and another top 50 using semantic vector search (Cosine Similarity). 

The `ReciprocalRankFusion` class provides a mathematically sound way to merge these disparate result sets and rank them based on their relative positions in the original lists.

```php
use IllumaLaw\HybridSearch\ReciprocalRankFusion;

// 1. Get results from Keyword Search
$keywordResults = Article::query()
    ->whereHybridFullText(['title', 'body'], 'authentication')
    ->take(50)
    ->get();

// 2. Get results from Vector Search (using laravel-vector-schema)
$vectorResults = Article::query()
    ->whereHybridVectorSimilarTo('embedding', $queryVector)
    ->take(50)
    ->get();

// 3. Combine and re-rank the results
$combined = ReciprocalRankFusion::combine(
    [$keywordResults, $vectorResults],
    k: 60 // Optional: RRF constant (default is 60)
);

// $combined is a unique Collection of Article models, 
// ordered by their computed RRF score.
```

## Testing

```bash
composer test
```

## Credits

- [illuma-law](https://github.com/illuma-law)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
