# Laravel RAG

![Tests](https://img.shields.io/github/actions/workflow/status/thaolaptrinh/laravel-rag/run-tests.yml?branch=main&label=tests)
![PHPStan](https://img.shields.io/github/actions/workflow/status/thaolaptrinh/laravel-rag/phpstan.yml?branch=main&label=PHPStan)
![License](https://img.shields.io/packagist/license/thaolaptrinh/laravel-rag)

Production-grade RAG (Retrieval-Augmented Generation) engine for Laravel — provider-agnostic, HTTP-based, extensible.

## Features

- **Document Ingestion**: Split documents into chunks, generate embeddings, store in pgvector
- **Semantic Search**: Cosine similarity search with metadata filtering
- **LLM Integration**: OpenAI-compatible API support (OpenAI, FPT Cloud, Ollama, vLLM, Supabase)
- **Idempotent Re-ingestion**: Skip unchanged documents using content hash
- **Queue Support**: Process large document sets asynchronously
- **Testing Helpers**: Fake mode for unit testing

## Requirements

- PHP 8.4+
- Laravel 11.0+
- PostgreSQL 13+ with pgvector extension

## Installation

```bash
composer require thaolaptrinh/laravel-rag
```

### Configuration

1. Add the RAG database connection to `config/database.php`:

```php
'rag' => [
    'driver' => 'pgsql',
    'host' => env('RAG_DB_HOST', '127.0.0.1'),
    'port' => env('RAG_DB_PORT', '5432'),
    'database' => env('RAG_DB_DATABASE', 'laravel'),
    'username' => env('RAG_DB_USERNAME', 'postgres'),
    'password' => env('RAG_DB_PASSWORD', ''),
    'prefix' => '',
    'search_path' => env('RAG_DB_SCHEMA', 'public'),
],
```

2. Publish and run migrations:

```bash
php artisan rag:install
```

Or manually:

```bash
php artisan vendor:publish --provider="Thaolaptrinh\Rag\RagServiceProvider" --tag="rag-config"
php artisan vendor:publish --provider="Thaolaptrinh\Rag\RagServiceProvider" --tag="rag-migrations"
php artisan migrate
```

### Environment Variables

```env
# Database (separate from app DB)
RAG_DB_CONNECTION=rag
RAG_DB_HOST=127.0.0.1
RAG_DB_PORT=5432
RAG_DB_DATABASE=laravel
RAG_DB_USERNAME=postgres
RAG_DB_PASSWORD=

# Embedding (OpenAI-compatible HTTP)
RAG_EMBEDDING_API_URL=https://api.openai.com/v1/embeddings
RAG_EMBEDDING_API_KEY=sk-...
RAG_EMBEDDING_MODEL=text-embedding-3-small
RAG_EMBEDDING_DIMENSIONS=1536
RAG_EMBEDDING_BATCH_SIZE=100
RAG_EMBEDDING_TIMEOUT=120

# LLM (OpenAI-compatible HTTP)
RAG_LLM_API_URL=https://api.openai.com/v1/chat/completions
RAG_LLM_API_KEY=sk-...
RAG_LLM_MODEL=gpt-4o-mini
RAG_LLM_MAX_OUTPUT_TOKENS=4096
RAG_LLM_CONTEXT_WINDOW=128000
RAG_LLM_TEMPERATURE=0.7
RAG_LLM_TIMEOUT=120

# Chunking
RAG_CHUNK_SIZE=1000
RAG_CHUNK_OVERLAP=200

# Document
RAG_DOCUMENT_MAX_CONTENT_LENGTH=100000

# Ingestion
RAG_INGESTION_SUB_BATCH_SIZE=10
RAG_INGESTION_PIPELINE_TIMEOUT=600

# Retrieval
RAG_RETRIEVAL_TOP_K=20
RAG_RETRIEVAL_MIN_SCORE=0.0
RAG_HNSW_EF_SEARCH=100

# Prompt
RAG_PROMPT_SYSTEM=You are a helpful assistant...
RAG_PROMPT_TOKENS_PER_CHAR=0.25

# Contextual Retrieval (Optional)
RAG_CONTEXTUAL_ENABLED=false
```

## Usage

### Ingest Documents

```php
use Thaolaptrinh\Rag\Data\Document;
use Thaolaptrinh\Rag\Rag;

// Single document
$result = Rag::ingest(Document::create('Your document content here', [
    'source' => 'manual',
    'file_id' => 123,
]));

// Multiple documents
$result = Rag::ingestMany([
    Document::create('Content 1', ['source' => 'pdf']),
    Document::create('Content 2', ['source' => 'pdf']),
]);
```

### Query

```php
// Simple query
$answer = Rag::query('What is this document about?');

// With options
$answer = Rag::query('What is this about?', [
    'top_k' => 10,
    'filters' => ['source' => 'pdf'],
    'system' => 'Custom system prompt',
]);

echo $answer->text;
echo $answer->traceId;

// Access sources
foreach ($answer->sources as $source) {
    echo $source->content;
    echo $source->score;
}
```

### Streaming

```php
Rag::queryStream('Tell me about...', function (string $token): void {
    echo $token;
});
```

### Delete

```php
// Delete single document
Rag::delete('document-id');

// Delete multiple
$count = Rag::deleteMany(['id-1', 'id-2']);

// Delete all
Rag::truncate();
```

### Queue Support

For large document sets, use queued ingestion:

```php
// Queue single document
Rag::ingestQueued($document);
Rag::ingestQueued($document, 'rag-embeddings');

// Queue multiple (one job per document)
Rag::ingestManyQueued($documents);
```

### Testing

```php
use Thaolaptrinh\Rag\Rag;
use Thaolaptrinh\Rag\Data\Document;

beforeEach(function () {
    Rag::fake();
});

it('ingests documents', function () {
    $result = Rag::ingest(Document::create('test content'));
    
    expect($result->ingested)->toBe(1);
    
    Rag::assertIngested('document-id');
});

it('queries the store', function () {
    $answer = Rag::query('What is Laravel?');
    
    expect($answer->text)->toBe('Fake response');
    
    Rag::assertQueried('What is Laravel?');
});
```

## Artisan Commands

```bash
# Ingest a document
php artisan rag:ingest "Document content" --id="doc-1" --metadata='{"source": "manual"}'

# Query
php artisan rag:query "What is this about?"

# Delete
php artisan rag:delete doc-1
php artisan rag:delete --all

# Install (publish config + run migrations)
php artisan rag:install
php artisan rag:install --without-migration
```

## Architecture

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for detailed architecture documentation.

### Key Design Decisions

- **Provider-agnostic**: Core never mentions specific providers (OpenAI, pgvector, etc.)
- **Batch operations**: Use `embedBatch()`, `storeMany()` for production performance
- **Idempotent ingestion**: Content hash check skips unchanged documents
- **Separate database**: Uses `RAG_DB_*` connection, not app's default
- **Hard delete**: No soft delete to avoid HNSW index bloat

## Testing

```bash
# Run tests
composer test

# Run PHPStan
composer analyse
```

## License

MIT License. See [LICENSE.md](LICENSE.md).
