---
name: rag-core
description: Core RAG development workflow - interfaces, ingestion, query, generation
license: MIT
compatibility: opencode
metadata:
  focus: production-readiness
  code-quality: strict
---

## What I Do

Guide complete RAG pipeline development with emphasis on provider abstraction, modular design, and production-grade code quality.

## Core Architecture

### Interfaces (Contracts Namespace)
```
Thaolaptrinh\Rag\Contracts\

├── DataSource        // Load data from sources (generic)
├── Chunker           // Split text into chunks
├── EmbeddingDriver   // Generate embeddings (provider abstraction)
├── VectorStore       // Store/search vectors (provider abstraction)
├── Retriever         // Retrieve relevant chunks (handles embedding internally)
├── PromptBuilder     // Build prompts for LLM
├── LlmDriver         // Generate responses (provider abstraction)
```

### Pipeline Stages

**Ingestion**:
```
DataSource → Chunker → EmbeddingDriver (batch) → VectorStore (batch)
```

**Query**:
```
Query → Retriever (handles embedding internally) → PromptBuilder → LlmDriver
```

### Critical Principles (NON-NEGOTIABLE)

#### 1. Provider Abstraction
- ALL provider interactions go through interfaces
- NO provider-specific logic in core domain
- Drivers are swappable via configuration

**Example**:
```php
// ✅ CORRECT
interface EmbeddingDriver {
    public function embed(string $text): array;
    public function embedBatch(array $texts): array; // PREFERRED for ingestion
}

// ❌ WRONG
class Chunker {
    public function embed(string $text): array {
        return OpenAI::embeddings()->create($text); // NO!
    }
}
```

#### 2. Generic Data Sources
- Use `DataSource` interface (not DocumentLoader)
- Support any data source, not just files
- Keep system extensible

```php
interface DataSource {
    /**
     * @return array<int, array{id: string, content: string, metadata: array<string, mixed>}>
     */
    public function load(string $source): array;
}

// MVP: TextDataSource
// Future: PdfDataSource, DatabaseDataSource, ApiDataSource
```

#### 3. Retrieval Abstraction
- Use `Retriever` interface (not direct VectorStore::search)
- Separates storage logic from retrieval logic
- Enables reranking, hybrid search, filtering

```php
interface Retriever {
    /**
     * @param string $query User query (raw text)
     * @return array<int, array{content: string, score: float, metadata: array<string, mixed>}>
     */
    public function retrieve(string $query, int $topK, array $filters = []): array;
}

// MVP: SimilarityRetriever (handles embedding internally)
// Future: HybridRetriever, RerankingRetriever, CachedRetriever
```

#### 4. Batch Operations (REQUIRED)
- Use `embedBatch()` instead of individual `embed()` calls
- Use `storeMany()` instead of individual `store()` calls
- Critical for production performance

## Code Quality Standards (NON-NEGOTIABLE)

### 1. Strict Typing
- **MUST** use `declare(strict_types=1);` at the top of every PHP file
- **MUST** use explicit parameter and return types on ALL methods
- **MUST** use `readonly` properties for constructor dependencies
- **MUST** avoid loosely-typed arrays in favor of specific array shapes

### 2. PHPStan Compatibility
- All code must pass PHPStan level 6+
- Use specific types instead of `mixed` where possible
- Avoid dynamic property access
- Use null-safe operators appropriately

### 3. Production Best Practices
- Batch operations preferred (embedBatch, storeMany)
- Structured logging with trace IDs
- Duplicate-safe database operations
- Error handling with custom exceptions
- Idempotent ingestion via unique constraints

### Example (CORRECT):
```php
<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Services;

use Thaolaptrinh\Rag\Contracts\{DataSource, Chunker, EmbeddingDriver, VectorStore};

class IngestionPipeline
{
    public function __construct(
        private readonly DataSource $dataSource,
        private readonly Chunker $chunker,
        private readonly EmbeddingDriver $embedder,
        private readonly VectorStore $store
    ) {}
    
    /**
     * Ingest data from a source
     * 
     * @param string $source Data source identifier
     * @return array{stored: int, errors: int, source: string}
     */
    public function ingest(string $source): array
    {
        // Implementation...
    }
}
```

## Implementation Phases

### Phase 1: Core Abstractions + Ingestion Foundation
- Define all core interfaces with explicit types
- Implement `TextDataSource`
- Implement `TextChunker` with metadata preservation
- Create database migration with unique constraints
- Validate each component independently

### Phase 2: Complete Ingestion
- Implement `OpenAIEmbeddingDriver` with embedBatch()
- Implement `PgVectorStore` with storeMany()
- Implement `IngestionPipeline` service with batch operations
- Create `php artisan rag:ingest` command
- Validate end-to-end ingestion

### Phase 3: Query + Generation
- Implement `SimilarityRetriever` (accepts query string)
- Implement `SimplePromptBuilder` with token limits
- Implement `OpenAILlmDriver`
- Implement `QueryPipeline` service
- Create `php artisan rag:query` command
- Validate end-to-end query

### Phase 4: Laravel Integration
- Create `RagServiceProvider`
- Create `Rag` facade
- Add `config/rag.php`
- Write comprehensive tests
- Document usage

## Common Pitfalls

### ❌ Provider Lock-In
```php
// WRONG - Provider in core service
class IngestionPipeline {
    private $client = new OpenAIClient(); // NO!
}

// CORRECT - Inject driver
class IngestionPipeline {
    public function __construct(
        private readonly EmbeddingDriver $driver
    ) {}
}
```

### ❌ File-Biased Design
```php
// WRONG - Assumes files
interface DocumentLoader {
    public function loadFile(string $path): array;
}

// CORRECT - Generic sources
interface DataSource {
    /**
     * @return array<int, array{id: string, content: string, metadata: array<string, mixed>}>
     */
    public function load(string $source): array;
}
```

### ❌ Direct Vector Access
```php
// WRONG - Skips retrieval abstraction
class QueryPipeline {
    $chunks = $this->vectorStore->search(...);
}

// CORRECT - Retriever abstraction
class QueryPipeline {
    $chunks = $this->retriever->retrieve($query, $topK);
}
```

### ❌ Single-Item Operations
```php
// WRONG - Inefficient
foreach ($chunks as $chunk) {
    $this->vectorStore->store($embedding, $content, $metadata);
}

// CORRECT - Batch operations
$items = [];
foreach ($chunks as $chunk) {
    $items[] = ['embedding' => $embed, 'content' => $chunk['content'], 'metadata' => $chunk['metadata']];
}
$this->vectorStore->storeMany($items);
```

## Namespace Structure

```
Thaolaptrinh\Rag\
├── Contracts\       # All interfaces with explicit types
├── Drivers\
│   ├── DataSource\
│   │   └── TextDataSource.php
│   ├── Embeddings\
│   │   └── OpenAIEmbeddingDriver.php
│   ├── VectorStores\
│   │   └── PgVectorStore.php
│   └── Llm\
│       └── OpenAILlmDriver.php
├── Services\
│   ├── IngestionPipeline.php
│   ├── QueryPipeline.php
│   ├── Retrievers\
│   │   └── SimilarityRetriever.php
│   └── Logging\
│       └── RagLogger.php
├── Exceptions\
│   ├── RagException.php
│   ├── EmbeddingFailedException.php
│   ├── StorageFailedException.php
│   ├── RetrievalFailedException.php
│   └── GenerationFailedException.php
├── Models\
├── Facades\
├── Commands\
└── Exceptions\
```

## When to Use Me

Use for:
- Designing RAG pipeline architecture
- Implementing interfaces with explicit types
- Creating driver abstractions
- Building ingestion/query pipelines
- Reviewing code for provider lock-in
- Ensuring code quality standards

## Validation Checklist

Before considering a component complete:
- [ ] File has `declare(strict_types=1);`
- [ ] All methods have explicit parameter types
- [ ] All methods have explicit return types
- [ ] Constructor dependencies use `readonly`
- [ ] Batch operations are used (embedBatch, storeMany)
- [ ] JSON_THROW_ON_ERROR is used for JSON decoding
- [ ] Custom exceptions are used for errors
- [ ] Structured logging includes trace IDs
- [ ] PHPStan level 6+ compatible
