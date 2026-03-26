# Laravel RAG

[![Latest Version on Packagist](https://img.shields.io/packagist/v/thaolaptrinh/laravel-rag.svg?style=flat-square)](https://packagist.org/packages/thaolaptrinh/laravel-rag)
[![Total Downloads](https://img.shields.io/packagist/dt/thaolaptrinh/laravel-rag.svg?style=flat-square)](https://packagist.org/packages/thaolaptrinh/laravel-rag)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%206-brightgreen.svg?style=flat-square)](https://phpstan.org/)

A production-ready, provider-agnostic RAG (Retrieval-Augmented Generation) package for Laravel. Build AI-powered applications with modular architecture, strict typing, and enterprise-grade code quality.

## Highlights

- **Provider Agnostic**: Easily swap between OpenAI, Anthropic, local models, and vector databases
- **Production Ready**: Batch operations, duplicate-safe inserts, structured logging with trace IDs
- **Type Safe**: Full strict typing with PHPStan level 6+ compatibility
- **Modular Design**: Clean interfaces for extensibility and testing
- **Soft Delete Support**: Preserve data integrity with soft deletes
- **Idempotent Ingestion**: Handle duplicate chunks gracefully

## Installation

```bash
composer require thaolaptrinh/laravel-rag
```

Publish the configuration:

```bash
php artisan vendor:publish --tag="rag-config"
```

Publish and run migrations:

```bash
php artisan vendor:publish --tag="rag-migrations"
php artisan migrate
```

## Configuration

Add your OpenAI API key to `.env`:

```env
OPENAI_API_KEY=sk-your-api-key-here
```

The package uses sensible defaults, but you can customize via `config/rag.php`:

```php
return [
    'embedding' => [
        'provider' => 'openai',
        'model' => 'text-embedding-3-small',
        'dimension' => 1536,
    ],
    'llm' => [
        'provider' => 'openai',
        'model' => 'gpt-4o-mini',
        'max_tokens' => 4096,
        'temperature' => 0.7,
    ],
    'vector_store' => [
        'provider' => 'pgvector',
        'table' => 'rag_chunks',
    ],
    // ... more options
];
```

## Usage

### Ingest Data

```bash
php artisan rag:ingest /path/to/document.txt
```

Or programmatically:

```php
use Thaolaptrinh\Rag\Facades\Rag;

$result = Rag::ingest('/path/to/document.txt');
// Returns: ['stored' => 15, 'errors' => 0, 'source' => '/path/to/document.txt']
```

### Query the System

```bash
php artisan rag:query "What is the main topic of the document?"
```

Or programmatically:

```php
use Thaolaptrinh\Rag\Facades\Rag;

$result = Rag::query('What is the main topic?');
// Returns: ['answer' => '...', 'chunks' => 5, 'query' => '...']
```

## Architecture

### Core Abstractions

The package is built around 7 core interfaces:

- **DataSource**: Load data from any source (files, APIs, databases)
- **Chunker**: Split content into manageable chunks
- **EmbeddingDriver**: Generate vector embeddings (OpenAI, Cohere, local)
- **VectorStore**: Store and search vectors (pgvector, Pinecone, Weaviate)
- **Retriever**: Retrieve relevant chunks (similarity, hybrid, reranking)
- **PromptBuilder**: Build prompts for LLMs with token limits
- **LlmDriver**: Generate responses (OpenAI, Anthropic, local models)

### Pipeline Flow

**Ingestion**:
```
DataSource → Chunker → EmbeddingDriver (batch) → VectorStore (batch)
```

**Query**:
```
Query → Retriever → PromptBuilder → LlmDriver → Response
```

## Code Quality Standards

All code follows strict production standards:

- ✅ `declare(strict_types=1);` in every file
- ✅ Explicit parameter and return types on all methods
- ✅ `readonly` properties for constructor dependencies
- ✅ PHPStan level 6+ compatible
- ✅ Batch operations (embedBatch, storeMany) for performance
- ✅ Structured logging with trace IDs
- ✅ Duplicate-safe database operations
- ✅ Custom exceptions for error handling

## Extending the Package

### Custom Data Source

```php
use Thaolaptrinh\Rag\Contracts\DataSource;

class PdfDataSource implements DataSource
{
    public function load(string $source): array
    {
        // Your PDF loading logic
        return [
            [
                'id' => Str::uuid()->toString(),
                'content' => $pdfText,
                'metadata' => ['source' => $source, 'type' => 'pdf']
            ]
        ];
    }
}
```

### Custom Vector Store

```php
use Thaolaptrinh\Rag\Contracts\VectorStore;

class PineconeVectorStore implements VectorStore
{
    public function storeMany(array $items): void
    {
        // Your Pinecone implementation
    }
    
    // ... implement other methods
}
```

## Testing

```bash
composer test
```

Run static analysis:

```bash
composer analyse
```

## Requirements

- PHP 8.2 or higher
- Laravel 10.x or 11.x
- PostgreSQL with pgvector extension (for vector storage)
- OpenAI API key (or compatible service)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- [Thao Nguyen](https://github.com/thaolaptrinh)
- [All Contributors](../../contributors)
