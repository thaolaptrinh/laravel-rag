# Laravel RAG — Architecture & Build Specification

> Version: 1.0.0 | Status: REVIEWED | Date: 2026-03-28

---

## Table of Contents

1. [Overview](#1-overview)
2. [Scope & Constraints](#2-scope--constraints)
3. [Design Decisions](#3-design-decisions)
4. [Domain Model](#4-domain-model)
5. [Pipeline Architecture](#5-pipeline-architecture)
6. [Interface Contracts](#6-interface-contracts)
7. [Driver Implementations](#7-driver-implementations)
8. [Database Schema](#8-database-schema)
9. [Configuration](#9-configuration)
10. [Error Handling](#10-error-handling)
11. [Logging & Observability](#11-logging--observability)
12. [Public API](#12-public-api)
13. [Package Structure](#13-package-structure)
14. [Testing Strategy](#14-testing-strategy)
15. [Quality Standards](#15-quality-standards)
 16. [Out of Scope (Future)](#16-out-of-scope-future)

---

## 1. Overview

**Package**: `thaolaptrinh/laravel-rag`
**Purpose**: Production-grade RAG engine for Laravel — provider-agnostic, HTTP-based, extensible
**PHP**: 8.4+ | **Laravel**: 11.0+ | **Database**: PostgreSQL with pgvector

### What it does

- **Ingest**: Accept `Document[]`, split into chunks, generate embeddings, store in pgvector
- **Query**: Accept a question, retrieve relevant chunks, build prompt, call LLM, return answer with sources
- **Delete**: Remove documents and all associated chunks by ID

### What it does NOT do

- Load files, parse PDFs — that's caller territory
- Manage API keys or accounts — user provides their own
- Run as a standalone service — it's a Laravel package

### Dependencies

```
laravel-rag (this package — core engine)
    ├── No dependencies on Filament, Spatie Media, or any specific framework
    └── Only depends on: illuminate/contracts, spatie/laravel-package-tools
```

---

## 2. Scope & Constraints

### MVP Scope

| Feature | Included |
|---------|----------|
| Document ingestion (raw string) | Yes |
| Text chunking (fixed-size with overlap) | Yes |
| Embedding via HTTP (OpenAI-compatible API) | Yes |
| Vector storage (pgvector) | Yes |
| Cosine similarity search with metadata filter | Yes |
| LLM generation via HTTP (OpenAI-compatible API) | Yes |
| Idempotent re-ingestion | Yes |
| Separate database connection (`RAG_DB_*`) | Yes |
| Structured logging with trace IDs | Yes |

### Constraints

| Constraint | Detail |
|-----------|--------|
| No vendor SDKs | All HTTP calls via `Illuminate\Support\Facades\Http` — no `openai-php`, no `pinecone-php` |
| No Eloquent models | All DB operations via `DB::connection('rag')->table()` — raw query builder |
| Provider-agnostic core | Core code never mentions "OpenAI", "Pinecone", etc. |
| PHPStan level 9 | Zero errors, zero `@phpstan-ignore` |
| Immutable domain objects | All value objects use PHP 8.2 `readonly` |
| Batch operations in ingestion | `embedBatch()`, `storeMany()` for ingestion — single `embed()` is fine for query-time |

### Assumptions

- PostgreSQL 13+ with pgvector extension installed
- Embedding model outputs normalized vectors (compatible with cosine distance)
- User has valid API keys for their chosen embedding and LLM providers
- Documents are plain text — no binary/media parsing in core

---

## 3. Design Decisions

### DD-01: Separate Document and Chunk tables (not single table)

**Context**: Some RAG systems store documents and chunks in the same table.
**Decision**: Two tables: `rag_documents` and `rag_chunks` with FK relationship.
**Rationale**: Document-level operations (delete by ID, check content hash, metadata query) become expensive on a single table with vector embeddings. Separation allows efficient document management without touching the vector index.

### DD-02: User-provided ID as primary key (no dual-ID)

**Context**: Some systems use internal UUID + external ID separately.
**Decision**: `rag_documents.id` is `VARCHAR(255)` — the user-provided ID is the primary key. No separate `external_id` column.
**Rationale**: Dual-ID (internal UUID + external ID) creates pervasive ambiguity — which ID does `deleteByDocumentId()` receive? How to resolve? The user-provided ID IS the primary key. No resolution needed.

### DD-03: Deterministic Chunk IDs (composite, not random)

**Context**: LlamaIndex uses random UUIDs for chunks. Haystack uses content-hash.
**Decision**: Chunk ID = `{documentId}::chunk::{index}`.
**Rationale**: Random UUIDs cause duplicate chunks on re-ingestion. Content-hash fails when metadata changes but content stays the same. Composite ID is deterministic, idempotent, and stable.

### DD-04: Embeddings NOT on domain objects

**Context**: LlamaIndex and Haystack put embeddings on Document/Node objects.
**Decision**: Embeddings exist only inside the pipeline and vector store. Domain objects (`Document`, `Chunk`) do not have an `embedding` field.
**Rationale**: Embeddings are 6KB+ per chunk (1536 floats × 4 bytes). They're an implementation detail of the vector store — never needed in application code. This matches LangChain's cleaner separation.

### DD-05: Content hash for skip-re-embed optimization

**Context**: Re-ingesting the same document should not re-call the embedding API.
**Decision**: `rag_documents.content_hash` stores SHA-256 of document content. On re-ingest, compare hashes. If unchanged, skip chunking + embedding entirely.
**Rationale**: Embedding API calls are expensive and slow. This optimization is free at the database level and saves significant time + cost.

### DD-06: Hard delete for chunks (no soft delete)

**Context**: Laravel convention is soft delete via `deleted_at`.
**Decision**: Chunks use hard delete. Documents do not have `deleted_at` either.
**Rationale**: Soft-deleted rows bloat the HNSW vector index (dead entries consume `ef_search` budget, degrading recall). Chunks are derived data — they can be regenerated from source. Audit trail belongs in application-level logging.

### DD-07: HNSW index (not IVFFlat)

**Context**: pgvector supports both IVFFlat and HNSW.
**Decision**: HNSW with `m=16, ef_construction=64, ef_search=100`.
**Rationale**: HNSW doesn't require training data (IVFFlat needs data loaded first). Recall stays stable as data grows. IVFFlat recall degrades if `lists != sqrt(rows)`. HNSW is the pgvector team's recommendation for production.

### DD-08: JSONB for metadata (not separate columns)

**Context**: Metadata could be stored as dedicated columns or JSONB.
**Decision**: Single `JSONB` column with GIN index (`jsonb_path_ops`) on chunks only.
**Rationale**: RAG metadata shape varies by source (DB rows have `table_name`, uploads have `file_type`, etc.). Separate columns require migrations for every new field. JSONB + GIN handles arbitrary filter queries. Document metadata GIN deferred to post-MVP — current queries only filter on chunk metadata.

### DD-09: Static `Rag` class (not Facade)

**Context**: Laravel packages can use Facades or static proxy classes.
**Decision**: Static `Rag` class with methods proxying to container-resolved pipelines.
**Rationale**: Scout and Horizon — the two most respected Laravel packages — both use static classes, not Facades. This avoids Facade testability concerns while keeping the API clean.

### DD-10: Heuristic token counting (not tiktoken)

**Context**: Exact token counting requires model-specific tokenizers (tiktoken for OpenAI).
**Decision**: Heuristic: `tokens = ceil(chars × tokens_per_char)`. Default `tokens_per_char = 0.25` (~4 chars per token for English).
**Rationale**: tiktoken adds a heavy dependency. Heuristic is good enough for context window management. The error margin (±20%) is acceptable because we truncate conservatively (leave buffer).

### DD-11: Separate database connection with `RAG_DB_*` prefix

**Context**: RAG could share the app's default DB connection.
**Decision**: Separate connection with `RAG_DB_HOST`, `RAG_DB_PORT`, `RAG_DB_DATABASE`, `RAG_DB_USERNAME`, `RAG_DB_PASSWORD`.
**Rationale**: User can point to same server (convenience) or separate service (Supabase, Neon, Railway). Package does not wrap or modify app DB config. No conflict with app migrations, query timeouts, or connection pooling.

---

## 4. Domain Model

### Diagram

```
User creates:    Document
                    │
Pipeline splits:  └──→ Chunk[] (0..N per document)
                           │
Embedding:              Chunk + embedding (transient, in pipeline only)
                           │
Stored:                 Chunk (content + metadata) + embedding (in pgvector)
                           │
Retrieval returns:      QueryResult (content + score + metadata)
                           │
Generation returns:     Answer (text + sources + traceId)
```

### Document

Core input. Created by the caller (app or service).

```php
namespace Thaolaptrinh\Rag\Data;

final readonly class Document
{
    private function __construct(
        public string $id,
        public string $content,
        public array $metadata,
    ) {}

    public static function create(string $content, array $metadata = [], ?string $id = null): self
    {
        return new self(
            id: $id ?? Str::uuid()->toString(),
            content: $content,
            metadata: $metadata,
        );
    }

    public function contentHash(): string
    {
        return hash('sha256', $this->content);
    }
}
```

**Design note**: `id` is user-provided or auto-UUID. This ID becomes the `rag_documents` primary key directly (no dual-ID).

### Chunk

Internal pipeline object. Created by `Chunker`. User never creates these directly. Inherits document metadata and adds chunk-specific fields.

```php
namespace Thaolaptrinh\Rag\Data;

final readonly class Chunk
{
    private function __construct(
        public string $id,
        public string $documentId,
        public string $content,
        public int $index,
        public array $metadata,
    ) {}

    public static function create(
        string $documentId,
        string $content,
        int $index,
        array $metadata = [],
    ): self {
        return new self(
            id: $documentId . '::chunk::' . $index,
            documentId: $documentId,
            content: $content,
            index: $index,
            metadata: $metadata,
        );
    }
}
```

**Design note**: Deterministic composite ID enables idempotent `INSERT ... ON CONFLICT DO UPDATE` in pgvector.

### QueryResult

Output of retrieval. Contains what's needed for prompt building and citation.

```php
namespace Thaolaptrinh\Rag\Data;

final readonly class QueryResult
{
    private function __construct(
        public string $content,
        public float $score,
        public array $metadata,
    ) {}

    public static function create(string $content, float $score, array $metadata = []): self
    {
        return new self(content: $content, score: $score, metadata: $metadata);
    }
}
```

### Answer

Final output of the query pipeline.

```php
namespace Thaolaptrinh\Rag\Data;

final readonly class Answer
{
    /**
     * @param  list<QueryResult>  $sources
     */
    private function __construct(
        public string $text,
        public array $sources,
        public string $traceId,
    ) {}

    /**
     * @param  list<QueryResult>  $sources
     */
    public static function create(string $text, array $sources, string $traceId): self
    {
        return new self(text: $text, sources: $sources, traceId: $traceId);
    }
}
```

### IngestionResult

Result of an ingestion operation.

```php
namespace Thaolaptrinh\Rag\Data;

final readonly class IngestionResult
{
    private function __construct(
        public int $ingested,
        public int $skipped,
        public int $errors,
        public string $traceId,
    ) {}

    public static function create(int $ingested, int $skipped, int $errors, string $traceId): self
    {
        return new self(ingested: $ingested, skipped: $skipped, errors: $errors, traceId: $traceId);
    }
}
```

---

## 5. Pipeline Architecture

### Ingestion Pipeline

```
Document[]
    │
    ▼
┌─────────────────────────────────────────────────────────┐
│ 0. Validate document sizes                               │
│    For each: strlen($content) ≤ max_content_length       │
│    Oversized → throw ChunkingFailedException immediately  │
├─────────────────────────────────────────────────────────┤
│ 1. Check content hash (batch)                            │
│    SELECT id, content_hash FROM rag_documents             │
│    WHERE id IN (...)                                     │
│    Partition into: [skip] and [process]                   │
│    Dispatch DocumentSkipped event per skipped document   │
├─────────────────────────────────────────────────────────┤
│ 2. Process in sub-batches (memory-safe)                  │
│    Split [process] into groups of {sub_batch_size} docs  │
│    For each sub-batch:                                    │
│    ┌─────────────────────────────────────────────────┐   │
│    │ 2a. Chunk documents                              │   │
│    │     Chunker::split() → Chunk[]                  │   │
│    ├─────────────────────────────────────────────────┤   │
│    │ 2b. Enrich chunks (optional)                    │   │
│    │     IF contextual.enabled:                      │   │
│    │       ContextEnricher::enrich(chunk, doc, meta) │   │
│    │       → prepends 50-100 tokens of context       │   │
│    │     ELSE: skip (chunks used as-is)              │   │
│    ├─────────────────────────────────────────────────┤   │
│    │ 2c. Generate embeddings (batch)                 │   │
│    │     EmbeddingDriver::embedBatch()               │   │
│    ├─────────────────────────────────────────────────┤   │
│    │ 2d. Store per-document (transaction + lock)     │   │
│    │     BEGIN TRANSACTION                            │   │
│    │     For each document in sub-batch:             │   │
│    │       pg_try_advisory_lock(doc_id)              │   │
│    │       Re-check hash (concurrent safety)         │   │
│    │       If unchanged → release lock, skip         │   │
│    │       Upsert rag_documents ON CONFLICT UPDATE   │   │
│    │       DELETE old chunks                         │   │
│    │       INSERT new chunks ON CONFLICT UPDATE      │   │
│    │       pg_advisory_unlock(doc_id)                │   │
│    │     COMMIT                                       │   │
│    ├─────────────────────────────────────────────────┤   │
│    │ 2e. Dispatch DocumentIngested event             │   │
│    │     Per-document: docId, chunkCount, traceId    │   │
│    └─────────────────────────────────────────────────┘   │
│    Check pipeline timeout → throw if exceeded            │
│    On per-document failure: log, increment errors,       │
│    continue (don't abort entire batch)                   │
├─────────────────────────────────────────────────────────┤
│ 3. Dispatch IngestionCompleted event                     │
│    ingested, skipped, errors, durationMs, traceId        │
└─────────────────────────────────────────────────────────┘
    │
    ▼
IngestionResult { ingested: int, skipped: int, errors: int, traceId: string }
```

**Key behaviors**:
- **Document size validation** runs FIRST — oversized documents throw `ChunkingFailedException` immediately before any API calls
- **Content hash check** runs before embedding — avoids unnecessary work
- **Sub-batch processing**: Documents are processed in configurable groups (`sub_batch_size`, default 10). Each sub-batch is chunked, embedded, and stored before the next begins. This prevents OOM when ingesting large document sets — only one sub-batch's worth of chunks + embeddings are in memory at any time
- **Concurrent ingestion safety**: Per-document advisory lock (`pg_try_advisory_lock`) + hash re-check inside the transaction. If another process ingested the same document while we were embedding, we skip (discarding the already-generated embeddings for that document) rather than wasting a store operation
- **Transaction boundaries**: Each document's upsert + chunk replacement is wrapped in a single transaction. If chunk storage fails, the document record is rolled back — no orphaned document without chunks
- **Pipeline timeout**: Configurable overall timeout (`pipeline_timeout`, default 600s). Checked between sub-batches. If exceeded, throws `ChunkingFailedException` with progress info (how many succeeded before timeout)
- **Batch embedding**: Within each sub-batch, chunks from all documents are combined into one `embedBatch()` call (up to `batch_size`). If the total exceeds `batch_size`, split into sequential HTTP calls
- **Error isolation**: Per-document try/catch — one failure does not abort the entire ingestion. Failed documents increment the `errors` counter and dispatch `DocumentIngestionFailed` event
- **Events**: `DocumentSkipped`, `DocumentIngested`, `DocumentIngestionFailed`, `IngestionCompleted` dispatched at appropriate points (see §11)
- **Contextual enrichment (optional)**: When `config('rag.contextual.enabled') = true`, each chunk is enriched with document-level context via `ContextEnricher` before embedding. This prepends 50-100 tokens of context explaining how the chunk relates to the overall document. Based on [Anthropic's Contextual Retrieval](https://www.anthropic.com/research/contextual-retrieval) research — reduces retrieval failure rate by 35%. Disabled by default. Uses the same `LlmDriver` already bound in the container.
- Idempotent: re-ingesting same document updates existing chunks, does not create duplicates

### Query Pipeline

```
"User question"
    │
    ▼
┌─────────────────────────────────────────────┐
│ 1. Retrieve relevant chunks                 │
│    Retriever::retrieve($question, $topK,     │
│                        $filters)            │
│    (internally: embed query + search)        │
├─────────────────────────────────────────────┤
│ 2. Build prompt                             │
│    PromptBuilder::build($chunks, $question, │
│                          $maxTokens)        │
├─────────────────────────────────────────────┤
│ 3. Generate answer via LLM                  │
│    LlmDriver::generateWithSystem()          │
└─────────────────────────────────────────────┘
    │
    ▼
Answer { text, sources, traceId }
```

**Key behaviors**:
- `Retriever` interface handles embedding internally — pipeline never calls `EmbeddingDriver::embed()` directly
- Retrieval and LLM are separate steps — caller could use only retrieval without LLM
- Prompt builder respects context window — truncates chunks that don't fit
- Trace ID generated at pipeline start, included in all logs
- Dispatches `QueryCompleted` event on success (see §11)

### Pipeline Independence

- Ingestion and Query pipelines are completely independent
- They share only interfaces (`EmbeddingDriver`, `VectorStore`)
- Query pipeline does NOT depend on Ingestion pipeline
- Each pipeline can be tested, deployed, and scaled independently

### Pipeline Events

All events use standard Laravel event dispatching. Users listen via `Event::listen()` or `Event::listen(DocumentIngested::class, fn($event) => ...)`.

| Event | Dispatched by | Properties | Use case |
|-------|--------------|------------|----------|
| `DocumentSkipped` | IngestionPipeline | `documentId`, `reason`, `traceId`, `createdAt` | Audit trail, monitoring skipped content |
| `DocumentIngested` | IngestionPipeline | `documentId`, `chunkCount`, `traceId`, `createdAt` | Trigger downstream actions (reindex, notify) |
| `DocumentIngestionFailed` | IngestionPipeline | `documentId`, `reason`, `traceId`, `throwable`, `createdAt` | Alerting, retry queues, dead-letter handling |
| `IngestionCompleted` | IngestionPipeline | `ingested`, `skipped`, `errors`, `durationMs`, `traceId`, `createdAt` | Batch completion notifications, metrics |
| `QueryCompleted` | QueryPipeline | `question`, `chunksRetrieved`, `durationMs`, `traceId`, `createdAt` | Query analytics, monitoring, rate limiting |

**Design note**: Events are dispatched at pipeline level, not at driver level. Drivers do not dispatch events. This keeps the event surface small and predictable. Events are dispatched synchronously — if async dispatch is needed, users can wrap in `event()` with `ShouldQueue` listeners.

---

## 6. Interface Contracts

All interfaces live in `Thaolaptrinh\Rag\Contracts`.

### Chunker

```php
interface Chunker
{
    /**
     * Split a document into chunks.
     * Each chunk inherits the document's metadata.
     * Implementations may add chunk-specific metadata (e.g., 'chunk_index', 'offset').
     *
     * @param  Document  $document  The document to split
     * @return list<Chunk>
     */
    public function split(Document $document): array;

    /**
     * Get configured chunk size in characters.
     *
     * @return int<1, max>
     */
    public function getChunkSize(): int;

    /**
     * Get configured overlap in characters.
     *
     * @return int<0, max>
     */
    public function getOverlap(): int;
}
```

### ContextEnricher

Optional preprocessing step that adds document-level context to each chunk before embedding. Based on [Anthropic's Contextual Retrieval](https://www.anthropic.com/research/contextual-retrieval) research — reduces retrieval failure rate by 35%. Disabled by default; enabled via `config('rag.contextual.enabled')`.

```php
interface ContextEnricher
{
    /**
     * Enrich a chunk with document-level context to improve retrieval accuracy.
     * The returned chunk has context prepended to its content.
     *
     * @param  Chunk  $chunk  The chunk to enrich
     * @param  string  $documentContent  Full document text for context
     * @param  array<string, mixed>  $documentMetadata  Document metadata
     * @return Chunk  New chunk with enriched content (original chunk unchanged)
     */
    public function enrich(Chunk $chunk, string $documentContent, array $documentMetadata): Chunk;
}
```

**Design note**: `ContextEnricher` is an optional step in the ingestion pipeline. When disabled (`config('rag.contextual.enabled') = false`), the pipeline skips enrichment entirely. When enabled, each chunk is enriched before embedding — the LLM generates 50-100 tokens of context explaining how the chunk relates to the overall document. This uses the same `LlmDriver` already bound in the container — no additional provider dependency.

### EmbeddingDriver

```php
interface EmbeddingDriver
{
    /**
     * Generate embedding for a single text.
     * Intended for query-time use (single question embedding).
     *
     * @return list<float>
     */
    public function embed(string $text): array;

    /**
     * Generate embeddings for multiple texts (batch).
     * Intended for ingestion — drivers MUST use batch HTTP calls.
     * If input exceeds configured batch_size, split into sequential batch calls.
     * If any batch fails, the entire call throws — partial results are not returned.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedBatch(array $texts): array;

    /**
     * Get the embedding dimension.
     *
     * @return int<1, max>
     */
    public function dimensions(): int;
}
```

### VectorStore

```php
interface VectorStore
{
    /**
     * Store multiple chunks with their embeddings (batch).
     * Implementations MUST use bulk INSERT for performance.
     *
     * @param  list<array{chunk: Chunk, embedding: list<float>}>  $items
     */
    public function storeMany(array $items): void;

    /**
     * Search for similar chunks by embedding vector.
     *
     * MVP supports equality filters only: ['source' => 'pdf']
     * Future: operator support via ['page' => ['$gt' => 10]]
     *
     * @param  list<float>  $queryEmbedding
     * @param  int<1, max>  $topK
     * @param  array<string, mixed>  $filters  Metadata key-value equality filters
     * @param  float  $minScore  Minimum similarity score threshold (0.0-1.0)
     * @return list<QueryResult>
     */
    public function search(array $queryEmbedding, int $topK, array $filters = [], float $minScore = 0.0): array;

    /**
     * Get existing documents by IDs for content hash comparison.
     * Used by ingestion pipeline to skip unchanged documents.
     *
     * @param  list<string>  $ids
     * @return list<array{id: string, content_hash: string}>
     */
    public function getDocumentsByIds(array $ids): array;

    /**
     * Delete all chunks for a document AND the document record itself.
     *
     * @return int  Number of deleted chunks (document record is deleted implicitly via FK cascade)
     */
    public function deleteByDocumentId(string $documentId): int;

    /**
     * Delete all chunks for multiple documents AND their document records.
     *
     * @param  list<string>  $documentIds
     * @return int  Number of deleted chunks
     */
    public function deleteByDocumentIds(array $documentIds): int;

    /**
     * Delete all documents and chunks.
     */
    public function truncate(): void;
}
```

### Retriever

```php
interface Retriever
{
    /**
     * Retrieve relevant chunks for a query.
     * Handles embedding internally — callers pass raw text, not pre-embedded vectors.
     *
     * @param  string  $query  The user's question (raw text, not pre-embedded)
     * @param  int<1, max>  $topK  Number of results to return
     * @param  array<string, mixed>  $filters  Metadata filters
     * @return list<QueryResult>
     */
    public function retrieve(string $query, int $topK, array $filters = []): array;
}
```

### LlmDriver

```php
interface LlmDriver
{
    /**
     * Generate a response from a prompt.
     */
    public function generate(string $prompt): string;

    /**
     * Generate a response with system message.
     */
    public function generateWithSystem(string $system, string $prompt): string;

    /**
     * Generate a response streaming tokens via callback.
     *
     * @param  callable(string): void  $callback
     */
    public function generateStream(string $prompt, callable $callback): string;

    /**
     * Generate streaming with system message.
     *
     * @param  callable(string): void  $callback
     */
    public function generateStreamWithSystem(string $system, string $prompt, callable $callback): string;

    /**
     * Get the model's total context window size (input + output).
     *
     * @return int<1, max>
     */
    public function getContextWindow(): int;

    /**
     * Get the maximum output tokens for the model.
     *
     * @return int<1, max>
     */
    public function getMaxOutputTokens(): int;
}
```

### PromptBuilder

```php
interface PromptBuilder
{
    /**
     * Build prompt from context and question.
     *
     * @param  list<QueryResult>  $context
     * @param  int<1, max>  $maxContextTokens  Token budget for context
     */
    public function build(array $context, string $question, int $maxContextTokens): string;

    /**
     * Build prompt with custom system instructions.
     *
     * @param  list<QueryResult>  $context
     */
    public function buildWithSystem(string $system, array $context, string $question, int $maxContextTokens): string;

    /**
     * Estimate token count for text.
     *
     * @return int<0, max>
     */
    public function estimateTokens(string $text): int;
}
```

---

## 7. Driver Implementations

### 7.0 ContextEnricher — LLM-based chunk enrichment

Uses the bound `LlmDriver` to generate document-level context for each chunk. Based on [Anthropic's Contextual Retrieval](https://www.anthropic.com/research/contextual-retrieval) research.

```php
class LlmContextEnricher implements ContextEnricher
{
    public function __construct(
        private readonly LlmDriver $llm,
    ) {}

    public function enrich(Chunk $chunk, string $documentContent, array $documentMetadata): Chunk
    {
        $context = $this->llm->generateWithSystem(
            'You are a helpful assistant. Given the following document and a chunk from it, '
            . 'provide a short, succinct context (50-100 tokens) that situates the chunk within '
            . 'the overall document. Answer only with the succinct context and nothing else.',
            "<document>\n{$documentContent}\n</document>\n\n"
            . "<chunk>\n{$chunk->content}\n</chunk>"
        );

        return Chunk::create(
            documentId: $chunk->documentId,
            content: $context . "\n\n" . $chunk->content,
            index: $chunk->index,
            metadata: $chunk->metadata,
        );
    }
}
```

**Key behaviors**:
- Uses the same `LlmDriver` already bound in the container — works with any OpenAI-compatible provider
- Output is ~50-100 tokens, prepended to chunk content before embedding
- Returns a new `Chunk` — original chunk is unchanged (immutable)
- Cost: ~$1.02 per million document tokens (with prompt caching at provider level)
- No Claude-specific dependency — any LLM works

**Configuration**:
```php
'contextual' => [
    'enabled' => env('RAG_CONTEXTUAL_ENABLED', false),
],
```

### 7.1 HttpEmbeddingDriver — OpenAI-compatible embedding via HTTP

Works with any provider that implements the OpenAI embeddings API format:
OpenAI, FPT Cloud (`mkp-api.fptcloud.com`), self-hosted Ollama, vLLM, Supabase, etc.

**API contract (expect)**:
```
POST {RAG_EMBEDDING_API_URL}
Authorization: Bearer {RAG_EMBEDDING_API_KEY}
Content-Type: application/json

{
  "model": "{RAG_EMBEDDING_MODEL}",
  "input": ["text 1", "text 2", ...],
  "dimensions": {RAG_EMBEDDING_DIMENSIONS},
  "encoding_format": "float"
}
```

**Response contract (expect)**:
```json
{
  "data": [
    { "embedding": [0.1, 0.2, ...] },
    { "embedding": [0.3, 0.4, ...] }
  ]
}
```

**Key behaviors**:
- Empty API key → throw `ConfigurationException` at construction time (config error, not runtime)
- HTTP timeout: configurable via `RAG_EMBEDDING_TIMEOUT` (default 120s)
- `embedBatch()`: single HTTP call with all texts (not loop of individual calls)
- `embed()`: delegates to `embedBatch([$text])[0]`
- Response validation: check `data` array exists, each item has `embedding` array
- `JSON_THROW_ON_ERROR` on all `json_decode` calls
- Batch size limit: configurable via `RAG_EMBEDDING_BATCH_SIZE` (default 100). If input exceeds, split into multiple sequential batch calls — results concatenated preserving input order. If any batch fails, the entire `embedBatch()` call throws — partial results are not returned. Previously embedded batches are discarded on failure.

**Retry policy**:
- 429 Too Many Requests: Retry with exponential backoff (1s, 2s, 4s), max 3 retries
- 500/502/503: Retry with exponential backoff (1s, 2s), max 2 retries
- 401/403: Do NOT retry — auth errors, throw `EmbeddingFailedException` immediately
- Timeouts: Do NOT retry — may indicate stuck requests, throw `EmbeddingFailedException`

### 7.2 HttpLlmDriver — OpenAI-compatible LLM via HTTP

Same approach — works with any OpenAI-compatible chat completions API.

**API contract (expect)**:
```
POST {RAG_LLM_API_URL}
Authorization: Bearer {RAG_LLM_API_KEY}
Content-Type: application/json

{
  "model": "{RAG_LLM_MODEL}",
  "messages": [
    {"role": "system", "content": "..."},
    {"role": "user", "content": "..."}
  ],
  "temperature": {RAG_LLM_TEMPERATURE},
  "max_tokens": {RAG_LLM_MAX_OUTPUT_TOKENS},
  "stream": true/false
}
```

**Response contract (expect)**:
```json
{
  "choices": [
    {
      "message": {"content": "answer text"},
      "delta": {"content": "token"}  // streaming only
    }
  ]
}
```

**Key behaviors**:
- `generate()` delegates to `generateWithSystem('You are a helpful assistant.', $prompt)`
- `generateStream()` and `generateStreamWithSystem()`: parse SSE `data: ` lines, call callback per token
- Empty API key → throw `ConfigurationException` at construction time (config error, not runtime)
- HTTP timeout: configurable via `RAG_LLM_TIMEOUT` (default 120s)
- `JSON_THROW_ON_ERROR` on all `json_decode` calls in stream parser
- Malformed SSE JSON → log warning with truncated raw line, skip silently (don't break the stream)
- `getContextWindow()` returns config value (model's total context window)
- `getMaxOutputTokens()` returns config value (requested max output tokens)

**Retry policy**: Same as HttpEmbeddingDriver (see 7.1).

### 7.3 PgVectorStore — PostgreSQL with pgvector

**Connection**: Uses `DB::connection($connectionName)` from config. Does NOT use app's default connection.

**Key behaviors**:

**storeMany()**: Single bulk `INSERT ... ON CONFLICT (document_id, chunk_index) DO UPDATE SET content = EXCLUDED.content, embedding = EXCLUDED.embedding, metadata = EXCLUDED.metadata, updated_at = NOW()`. Not N individual inserts.

**search()**:
```sql
SET LOCAL hnsw.ef_search = {config value};

SELECT content, metadata,
       1 - (embedding <=> '[...]'::vector) AS score
FROM rag_chunks
WHERE metadata->>'key' = 'value'   -- per filter
ORDER BY embedding <=> '[...]'::vector
LIMIT {topK}
```
- Sets `hnsw.ef_search` per-query based on config (inside a transaction)
- Applies metadata filters as `WHERE metadata->>'key' = 'value'` (equality only for MVP)
- Supports `$minScore` parameter — filters out results below threshold

**getDocumentsByIds()**: Batch fetch existing documents for content hash comparison:
```sql
SELECT id, content_hash FROM rag_documents WHERE id IN (?)
```
- Used by ingestion pipeline to skip unchanged documents

**deleteByDocumentId()**: `DELETE FROM rag_chunks WHERE document_id = ?`. Hard delete. Returns count of deleted rows. Also deletes the document record from `rag_documents`.

**deleteByDocumentIds()**: Batch delete both chunks and documents.

**truncate()**: Deletes all chunks, then all documents.

**Error handling**: Catches `QueryException`, wraps in `StorageFailedException`.

### 7.4 SimilarityRetriever — Default retriever implementation

Implements `Retriever` interface. Uses `EmbeddingDriver` + `VectorStore`.

```php
class SimilarityRetriever implements Retriever
{
    public function __construct(
        private readonly EmbeddingDriver $embedder,
        private readonly VectorStore $store,
        private readonly float $minScore,
    ) {}

    public function retrieve(string $query, int $topK, array $filters = []): array
    {
        $queryEmbedding = $this->embedder->embed($query);
        return $this->store->search($queryEmbedding, $topK, $filters, $this->minScore);
    }
}
```

**Configuration**: `$minScore` comes from `config('rag.retrieval.min_score')` (env: `RAG_RETRIEVAL_MIN_SCORE`, default `0.0`). Injected via constructor by the service provider.

**Error handling**: Wraps `EmbeddingFailedException` from embed and `StorageFailedException` from search in `RetrievalFailedException`.

**Design note**: `VectorStore` is focused on chunk/embedding storage + search. `Retriever` is the retrieval strategy layer that combines embedding + search. This keeps `VectorStore` swappable without affecting retrieval logic.

---

## 8. Database Schema

### Extension

```sql
CREATE EXTENSION IF NOT EXISTS vector;
```

### Table: `rag_documents`

```sql
CREATE TABLE rag_documents (
    id           VARCHAR(255) PRIMARY KEY,
    content_hash VARCHAR(64) NOT NULL,
    metadata     JSONB NOT NULL DEFAULT '{}',
    created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
```

| Column | Type | Purpose |
|--------|------|---------|
| `id` | VARCHAR(255) PK | User-provided document ID (e.g., `post_123`). No dual-ID. |
| `content_hash` | VARCHAR(64) | SHA-256 of content — skip re-embed if unchanged |
| `metadata` | JSONB | Arbitrary metadata from caller |
| `created_at` | TIMESTAMPTZ | Audit |
| `updated_at` | TIMESTAMPTZ | Audit |

**Indexes**:
- Primary key on `id` — user-provided ID, no separate `external_id`

**Design note**: No GIN index on `rag_documents.metadata` for MVP — current query patterns only filter on `rag_chunks.metadata`.

### Table: `rag_chunks`

```sql
CREATE TABLE rag_chunks (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    document_id VARCHAR(255) NOT NULL REFERENCES rag_documents(id) ON DELETE CASCADE,
    content     TEXT NOT NULL,
    chunk_index INTEGER NOT NULL,
    embedding   vector NOT NULL,
    metadata    JSONB NOT NULL DEFAULT '{}',
    content_tsv TSVECTOR GENERATED ALWAYS AS (to_tsvector('english', content)) STORED,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_chunks_document_chunk UNIQUE (document_id, chunk_index),
    CONSTRAINT chk_chunk_index CHECK (chunk_index >= 0)
);
```

| Column | Type | Purpose |
|--------|------|---------|
| `id` | UUID | Internal primary key (auto-generated) |
| `document_id` | VARCHAR(255) | FK to rag_documents — CASCADE on delete |
| `content` | TEXT | Chunk text content |
| `chunk_index` | INTEGER | Position in document (0-indexed). INTEGER, not SMALLINT — avoids 32K limit for large documents. |
| `embedding` | vector | pgvector embedding — dimension from config |
| `metadata` | JSONB | Inherited from document + chunk-specific metadata |
| `content_tsv` | TSVECTOR | Auto-generated full-text search vector (English) |
| `created_at` | TIMESTAMPTZ | Audit |
| `updated_at` | TIMESTAMPTZ | Audit |

**Indexes**:
- `uq_chunks_document_chunk` — UNIQUE on `(document_id, chunk_index)` — idempotent upsert
- B-tree on `document_id` — fast JOIN/DELETE by document
- HNSW on `embedding` with `vector_cosine_ops` — similarity search
- GIN on `metadata` with `jsonb_path_ops` — metadata filtering
- GIN on `content_tsv` — full-text search for hybrid retrieval (semantic + lexical)

Note: The B-tree index on `document_id` is created automatically by the FK constraint.

### Vector Index

```sql
CREATE INDEX CONCURRENTLY idx_chunks_embedding_hnsw
    ON rag_chunks USING hnsw (embedding vector_cosine_ops)
    WITH (m = 16, ef_construction = 64);

CREATE INDEX CONCURRENTLY idx_chunks_metadata_gin
    ON rag_chunks USING gin (metadata jsonb_path_ops);

CREATE INDEX CONCURRENTLY idx_chunks_content_tsv_gin
    ON rag_chunks USING gin (content_tsv);
```

**Note**: `CREATE INDEX CONCURRENTLY` cannot run in a transaction. Must be in a separate migration with `protected bool $withinTransaction = false;`.

---

## 9. Configuration

### Environment Variables

```env
# Database (separate from app DB)
RAG_DB_CONNECTION=rag
RAG_DB_HOST=127.0.0.1
RAG_DB_PORT=5432
RAG_DB_DATABASE=laravel
RAG_DB_USERNAME=postgres
RAG_DB_PASSWORD=
RAG_DB_SCHEMA=public

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

# Logging
RAG_LOG_CHANNEL=stack
```

### config/rag.php Structure

```php
return [
    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    */
    'database' => [
        'connection' => env('RAG_DB_CONNECTION', 'rag'),
        'documents_table' => 'rag_documents',
        'chunks_table' => 'rag_chunks',
    ],

    /*
    |--------------------------------------------------------------------------
    | Embedding Driver
    |--------------------------------------------------------------------------
    */
    'embedding' => [
        'api_url' => env('RAG_EMBEDDING_API_URL', 'https://api.openai.com/v1/embeddings'),
        'api_key' => env('RAG_EMBEDDING_API_KEY', ''),
        'model' => env('RAG_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'dimensions' => (int) env('RAG_EMBEDDING_DIMENSIONS', 1536),
        'batch_size' => (int) env('RAG_EMBEDDING_BATCH_SIZE', 100),
        'timeout' => (int) env('RAG_EMBEDDING_TIMEOUT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | LLM Driver
    |--------------------------------------------------------------------------
    */
    'llm' => [
        'api_url' => env('RAG_LLM_API_URL', 'https://api.openai.com/v1/chat/completions'),
        'api_key' => env('RAG_LLM_API_KEY', ''),
        'model' => env('RAG_LLM_MODEL', 'gpt-4o-mini'),
        'max_output_tokens' => (int) env('RAG_LLM_MAX_OUTPUT_TOKENS', 4096),
        'context_window' => (int) env('RAG_LLM_CONTEXT_WINDOW', 128000),
        'temperature' => (float) env('RAG_LLM_TEMPERATURE', 0.7),
        'timeout' => (int) env('RAG_LLM_TIMEOUT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Chunking
    |--------------------------------------------------------------------------
    */
    'chunking' => [
        'chunk_size' => (int) env('RAG_CHUNK_SIZE', 1000),
        'overlap' => (int) env('RAG_CHUNK_OVERLAP', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Document
    |--------------------------------------------------------------------------
    */
    'document' => [
        'max_content_length' => (int) env('RAG_DOCUMENT_MAX_CONTENT_LENGTH', 100_000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ingestion
    |--------------------------------------------------------------------------
    */
    'ingestion' => [
        'sub_batch_size' => (int) env('RAG_INGESTION_SUB_BATCH_SIZE', 10),
        'pipeline_timeout' => (int) env('RAG_INGESTION_PIPELINE_TIMEOUT', 600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retrieval
    |--------------------------------------------------------------------------
    */
    'retrieval' => [
        'top_k' => (int) env('RAG_RETRIEVAL_TOP_K', 20),
        'min_score' => (float) env('RAG_RETRIEVAL_MIN_SCORE', 0.0),
        'hnsw_ef_search' => (int) env('RAG_HNSW_EF_SEARCH', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Prompt
    |--------------------------------------------------------------------------
    */
    'prompt' => [
        'system' => env('RAG_PROMPT_SYSTEM', 'You are a helpful assistant. Answer the question based only on the provided context. If the context does not contain enough information, say so.'),
        'tokens_per_char' => (float) env('RAG_PROMPT_TOKENS_PER_CHAR', 0.25),
    ],

    /*
    |--------------------------------------------------------------------------
    | Contextual Retrieval (Optional)
    |--------------------------------------------------------------------------
    */
    'contextual' => [
        'enabled' => env('RAG_CONTEXTUAL_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'channel' => env('RAG_LOG_CHANNEL', 'stack'),
    ],
];
```

### Database Connection Setup

Package publishes a stub in `config/rag.php` instructing user to add the `rag` connection to their `config/database.php`. The connection is NOT added automatically — user must configure it.

**Note**: `RAG_DB_SCHEMA` is set via the `search_path` key in the user's `config/database.php` `rag` connection (default: `public`). It is NOT a package config key — the package reads it transparently through Laravel's database connection.

**Published stub** (shown in `config/rag.php` after publish):
```php
// Add this to config/database.php connections array:
/*
'rag' => [
    'driver' => 'pgsql',
    'host' => env('RAG_DB_HOST', env('DB_HOST', '127.0.0.1')),
    'port' => env('RAG_DB_PORT', env('DB_PORT', '5432')),
    'database' => env('RAG_DB_DATABASE', env('DB_DATABASE', 'forge')),
    'username' => env('RAG_DB_USERNAME', env('DB_USERNAME', 'forge')),
    'password' => env('RAG_DB_PASSWORD', env('DB_PASSWORD', '')),
    'prefix' => '',
    'prefix_indexes' => '',
    'search_path' => env('RAG_DB_SCHEMA', 'public'),
],
*/
```

---

## 10. Error Handling

### Exception Hierarchy

```
RagException (abstract, extends \RuntimeException)
├── EmbeddingFailedException    — Embedding API errors (runtime)
├── StorageFailedException       — Vector store read/write errors (runtime)
├── RetrievalFailedException     — Search/embed failure in Retriever (runtime)
├── GenerationFailedException    — LLM API errors (runtime)
├── ChunkingFailedException      — Text splitting / document size errors (runtime)
├── DocumentNotFoundException    — Document ID not found
└── ConfigurationException       — Invalid or missing config (startup)
```

### ConfigurationException

Thrown by the service provider during registration or by drivers at construction time:
- Empty `api_key` for embedding or LLM drivers
- Invalid `dimensions` (not positive integer)
- Invalid `chunk_size` or `overlap` (overlap >= chunk_size, negative values)
- Missing `rag` database connection
- pgvector extension not detected

### Rules

- All exceptions extend `RagException`
- Each exception has a `static create(string $reason, ?\Throwable $previous = null): self` named constructor (except `DocumentNotFoundException` which takes `string $id`)
- Pipeline exceptions always chain the original exception as `$previous`
- Exceptions are thrown, NOT caught inside pipelines — caller decides error handling
- No `\RuntimeException`, `\InvalidArgumentException`, or generic PHP exceptions in package code

### Example

```php
// Throwing with previous exception
throw EmbeddingFailedException::create(
    "API request failed: {$response->status()} {$response->body()}",
    $e
);

// Named constructor
class EmbeddingFailedException extends RagException
{
    public static function create(string $reason, ?\Throwable $previous = null): self
    {
        return new self("Embedding failed: {$reason}", 0, $previous);
    }
}
```

---

## 11. Logging & Observability

### Trace ID

Every pipeline execution generates a UUID trace ID. This ID is:
- Included in every log entry
- Returned in `Answer::traceId`
- Returned in `IngestionResult::traceId`

### Log Structure

All log entries use structured context:

```php
$this->logger->info('RAG pipeline step', [
    'trace_id' => $traceId,
    'pipeline' => 'ingestion',       // or 'query'
    'step' => 'embed',                // or 'chunk', 'store', 'retrieve', 'generate'
    'document_id' => $documentId,    // when applicable
    'documents_count' => $count,     // when applicable
    'chunks_count' => $chunkCount,   // when applicable
    'duration_ms' => $duration,       // when applicable
]);
```

### Log Levels

| Level | When |
|-------|------|
| `info` | Pipeline start/end, document ingested, query answered |
| `warning` | Content hash unchanged (skip), API retry, batch split, malformed SSE chunk |
| `error` | Exception caught, API failure, store failure |

### Log Channel

Configurable via `RAG_LOG_CHANNEL` (defaults to `stack`).

### RagLogger

Internal service responsible for structured logging. Injected into pipelines via constructor.

```php
class RagLogger
{
    public function __construct(private readonly string $channel) {}

    public function ingestionStart(string $traceId, int $documentCount): void;
    public function ingestionSkipped(string $traceId, string $documentId, string $reason): void;
    public function ingestionComplete(string $traceId, int $ingested, int $skipped, int $errors, int $durationMs): void;
    public function queryStart(string $traceId, string $question): void;
    public function queryComplete(string $traceId, int $chunksRetrieved, int $durationMs): void;
    public function error(string $traceId, string $pipeline, string $step, string $message, ?\Throwable $previous = null): void;
}
```

All methods include `trace_id`, `pipeline`, `step` in the log context. The `error()` method accepts an optional `$previous` exception for stack trace logging.

---

## 12. Public API

### Static `Rag` Class

```php
use Thaolaptrinh\Rag\Rag;

// Ingest one document
$result = Rag::ingest(Document::create('content here', ['source' => 'manual']));

// Ingest multiple documents
$result = Rag::ingestMany([
    Document::create('content 1', ['source' => 'pdf', 'file_id' => 1]),
    Document::create('content 2', ['source' => 'pdf', 'file_id' => 2]),
]);

// Query with question (returns full answer)
$answer = Rag::query('What is this document about?');

// Query with options
$answer = Rag::query('What is this about?', [
    'top_k' => 10,
    'filters' => ['source' => 'pdf'],
    'system' => 'Custom system prompt',
]);

// Stream query response
Rag::queryStream('Tell me about...', function (string $token): void {
    echo $token;
});

// Delete a document (returns true if found, throws if not)
Rag::delete('media-id-1');

// Delete multiple documents (returns count of deleted)
$count = Rag::deleteMany(['media-id-1', 'media-id-2']);

// Delete all documents and chunks
Rag::truncate();
```

### Method Signatures

```php
class Rag
{
    /**
     * Ingest a single document.
     * @throws StorageFailedException On database or embedding failure
     */
    public static function ingest(Document $document): IngestionResult;

    /**
     * Ingest multiple documents (batch).
     * @throws StorageFailedException On database or embedding failure
     */
    public static function ingestMany(array $documents): IngestionResult;

    /**
     * Query with question, returns full answer with sources.
     * @throws RetrievalFailedException On search failure
     * @throws GenerationFailedException On LLM failure
     */
    public static function query(string $question, array $options = []): Answer;

    /**
     * Query with streaming response.
     * @param  callable(string): void  $callback  Called for each token
     * @throws RetrievalFailedException On search failure
     * @throws GenerationFailedException On LLM failure
     */
    public static function queryStream(string $question, callable $callback, array $options = []): Answer;

    /**
     * Delete a document and all its chunks from rag_documents.
     * @return bool True if document was found and deleted
     * @throws DocumentNotFoundException If document not found
     */
    public static function delete(string $documentId): bool;

    /**
     * Delete multiple documents and their chunks from rag_documents.
     * @param  list<string>  $documentIds
     * @return int  Number of documents deleted
     */
    public static function deleteMany(array $documentIds): int;

    /**
     * Delete all documents and chunks.
     */
    public static function truncate(): void;

    /**
     * Queue ingestion for later processing.
     * Dispatches a single RagIngestJob per document to the default queue.
     * Does not block — returns immediately.
     *
     * @param  string|null  $queue  Queue name (null = default)
     * @return void
     */
    public static function ingestQueued(Document $document, ?string $queue = null): void;

    /**
     * Queue batch ingestion. Dispatches one RagIngestJob per document.
     *
     * @param  list<Document>  $documents
     * @param  string|null  $queue  Queue name (null = default)
     * @return void
     */
    public static function ingestManyQueued(array $documents, ?string $queue = null): void;

    /**
     * Replace the RAG driver with fakes for testing.
     * Returns deterministic results, records method calls for assertions.
     * Call without arguments to activate, call Rag::fake() with no args to reset.
     *
     * @return void
     */
    public static function fake(): void;

    /**
     * Assert a document was ingested during fake mode.
     *
     * @return void
     */
    public static function assertIngested(string $documentId): void;

    /**
     * Assert a query was made during fake mode.
     *
     * @return void
     */
    public static function assertQueried(string $question): void;

    /**
     * Assert a document was deleted during fake mode.
     *
     * @return void
     */
    public static function assertDeleted(string $documentId): void;
}
```

### RagManager (Internal)

Container-resolved singleton registered as `rag`. Holds pipeline instances and is used by the `Rag` static class to delegate method calls. Not part of the public API — users interact only with `Rag::*` static methods.

```php
class RagManager
{
    public function __construct(
        private readonly IngestionPipeline $ingestionPipeline,
        private readonly QueryPipeline $queryPipeline,
    ) {}

    public function ingest(Document $document): IngestionResult { ... }
    public function ingestMany(array $documents): IngestionResult { ... }
    public function query(string $question, array $options): Answer { ... }
    public function queryStream(string $question, callable $callback, array $options): Answer { ... }
    public function delete(string $documentId): bool { ... }
    public function deleteMany(array $documentIds): int { ... }
    public function truncate(): void { ... }
}
```

### IngestionResult Detail

| Field | Meaning |
|-------|---------|
| `ingested` | Number of documents that were chunked + embedded + stored |
| `skipped` | Number of documents skipped (content hash unchanged) |
| `errors` | Number of documents that failed during ingestion |
| `traceId` | UUID for tracing this operation in logs |

### Queue Support

For production workloads (50+ documents), ingestion should be queued to avoid blocking the HTTP request. The package provides `Rag::ingestQueued()` and `Rag::ingestManyQueued()` which dispatch `RagIngestJob` to Laravel's queue system.

```php
use Thaolaptrinh\Rag\Rag;
use Thaolaptrinh\Rag\Jobs\RagIngestJob;

// Queue a single document
Rag::ingestQueued($document);
Rag::ingestQueued($document, 'rag-embeddings'); // specific queue

// Queue multiple documents (one job per document)
Rag::ingestManyQueued($documents);
Rag::ingestManyQueued($documents, 'rag-embeddings');
```

**RagIngestJob**:
- Implements `ShouldQueue`
- Serialized properties: `documentId`, `documentContent`, `documentMetadata` (no object serialization — values only)
- Uses `retry_until` based on pipeline timeout config
- Dispatches pipeline events on success/failure
- Failed jobs are handled by Laravel's standard retry mechanism

**Design note**: One job per document (not one job per batch) ensures granular retry — a single failing document doesn't block the entire batch. The sub-batch processing happens inside the pipeline, not at the job level.

### Testing Helpers

The package provides `Rag::fake()` for testing user applications without real API calls or database connections. This follows the same pattern as `Http::fake()`, `Mail::fake()`, and `Scout::fake()`.

```php
use Thaolaptrinh\Rag\Rag;

// Activate fake mode
Rag::fake();

// Ingest returns deterministic IngestionResult
$result = Rag::ingest(Document::create('test content'));
// $result->ingested === 1, $result->skipped === 0, $result->errors === 0

// Query returns deterministic Answer
$answer = Rag::query('What is this about?');
// $answer->text === 'Fake response', $answer->sources === []

// Assert methods were called
Rag::assertIngested('document-id');
Rag::assertQueried('What is this about?');
Rag::assertDeleted('document-id');

// Reset fake
Rag::fake(); // call again to reset recorded calls
```

**Implementation**: `Rag::fake()` swaps the `rag` singleton in the container with a `FakeRagManager` that records calls and returns deterministic results. The fake is automatically reset between test cases via PHPUnit's `tearDown`.

---

## 13. Package Structure

```
src/
├── Contracts/                     ← All interfaces
│   ├── Chunker.php
│   ├── ContextEnricher.php
│   ├── EmbeddingDriver.php
│   ├── LlmDriver.php
│   ├── PromptBuilder.php
│   ├── Retriever.php
│   └── VectorStore.php
│
├── Data/                          ← Domain value objects
│   ├── Answer.php
│   ├── Chunk.php
│   ├── Document.php
│   ├── IngestionResult.php
│   └── QueryResult.php
│
├── Drivers/                       ← Interface implementations
│   ├── Embeddings/
│   │   └── HttpEmbeddingDriver.php
│   ├── Llm/
│   │   └── HttpLlmDriver.php
│   ├── VectorStores/
│   │   └── PgVectorStore.php
│   └── Enrichment/
│       └── LlmContextEnricher.php
│
├── Services/                      ← Business logic
│   ├── Chunking/
│   │   └── FixedSizeChunker.php
│   ├── Retrieving/
│   │   └── SimilarityRetriever.php
│   ├── Prompt/
│   │   └── SimplePromptBuilder.php
│   ├── IngestionPipeline.php
│   ├── QueryPipeline.php
│   └── RagLogger.php
│
├── Exceptions/                    ← Domain exceptions
│   ├── RagException.php
│   ├── EmbeddingFailedException.php
│   ├── StorageFailedException.php
│   ├── RetrievalFailedException.php
│   ├── GenerationFailedException.php
│   ├── ChunkingFailedException.php
│   ├── DocumentNotFoundException.php
│   └── ConfigurationException.php
│
├── Commands/                      ← Artisan commands
│   ├── RagIngestCommand.php
│   ├── RagQueryCommand.php
│   ├── RagDeleteCommand.php
│   └── RagInstallCommand.php
│
├── Events/                        ← Pipeline lifecycle events
│   ├── DocumentSkipped.php
│   ├── DocumentIngested.php
│   ├── DocumentIngestionFailed.php
│   ├── IngestionCompleted.php
│   └── QueryCompleted.php
│
├── Jobs/                          ← Queueable jobs
│   └── RagIngestJob.php
│
├── Testing/                       ← Test helpers for user applications
│   ├── FakeRagManager.php
│   └── InMemoryVectorStore.php
│
├── Rag.php                        ← Static entry point (NOT a Facade)
├── RagManager.php                 ← Container manager
└── RagServiceProvider.php         ← Laravel service provider
```

### Tests

```
tests/
├── Unit/
│   ├── Data/
│   │   ├── DocumentTest.php
│   │   ├── ChunkTest.php
│   │   └── AnswerTest.php
│   ├── Services/
│   │   ├── FixedSizeChunkerTest.php
│   │   ├── SimplePromptBuilderTest.php
│   │   ├── RagLoggerTest.php
│   │   ├── IngestionPipelineTest.php
│   │   └── QueryPipelineTest.php
│   ├── Drivers/
│   │   ├── HttpEmbeddingDriverTest.php
│   │   ├── HttpLlmDriverTest.php
│   │   └── PgVectorStoreTest.php
│   └── Exceptions/
│       └── ExceptionsTest.php
│   ├── Events/
│   │   └── EventsTest.php
│   └── Jobs/
│       └── RagIngestJobTest.php
├── Integration/
│   ├── IngestionPipelineTest.php
│   └── QueryPipelineTest.php
├── Feature/
│   ├── Commands/
│   │   ├── RagIngestCommandTest.php
│   │   ├── RagQueryCommandTest.php
│   │   └── RagInstallCommandTest.php
│   └── RagApiTest.php
├── ArchTest.php                   ← Architecture rules
├── Pest.php
└── TestCase.php
```

---

## 14. Testing Strategy

### Unit Tests

| Component | Strategy |
|-----------|----------|
| Domain objects (Document, Chunk, etc.) | Pure PHP, no mocks |
| FixedSizeChunker | Real splitting, assert boundaries and overlap |
| SimplePromptBuilder | Real token counting, assert truncation |
| Pipelines | Mock all driver interfaces via Pest |
| HttpEmbeddingDriver | Mock HTTP via `Http::fake()` |
| HttpLlmDriver | Mock HTTP via `Http::fake()` |
| Exceptions | Assert hierarchy, named constructors, `$previous` chaining |

### Integration Tests

| Component | Strategy |
|-----------|----------|
| IngestionPipeline | Mock `VectorStore` + Mock `EmbeddingDriver` (test pipeline orchestration) |
| QueryPipeline | Mock `VectorStore` + Mock `EmbeddingDriver` + Mock `LlmDriver` (test pipeline orchestration) |

**Note**: pgvector operations (similarity search, vector type) cannot be tested on SQLite. All pipeline integration tests mock `VectorStore` to test orchestration logic only. PgVectorStore-specific tests (real INSERT, real `<=>` search) require a real PostgreSQL with pgvector extension — run in CI only.

### Architecture Tests

```php
// ArchTest.php
// Note: Pest Arch plugin does not have toOnlyUseInterfaces() method
// Use manual checks instead or remove this test

it('services do not depend on concrete driver implementations')
    ->expect('Thaolaptrinh\Rag\Services')
    ->not->toUse('Thaolaptrinh\Rag\Drivers');

it('drivers implement their corresponding contracts')
    ->expect('Thaolaptrinh\Rag\Drivers\Embeddings\HttpEmbeddingDriver')
    ->toImplement(EmbeddingDriver::class);

it('all exceptions extend RagException')
    ->expect('Thaolaptrinh\Rag\Exceptions')
    ->toExtend(RagException::class);
```

### Test Environment

```yaml
# .github/workflows/run-tests.yml
services:
  postgres:
    image: pgvector/pgvector:pg16
    env:
      POSTGRES_DB: testing
      POSTGRES_USER: testing
      POSTGRES_PASSWORD: testing
    ports: ['5432:5432']
```

### CI Pipeline

```
1. PHPStan level 9 (0 errors)
2. Rector dry-run (0 changes)
3. Pest unit tests (all pass)
4. Pest architecture tests (all pass)
5. Pest integration tests with pgvector service (PgVectorStore real tests only)
```

---

## 15. Quality Standards

### PHPStan Level 9

- Zero errors, zero warnings
- Zero `@phpstan-ignore` comments
- All config() values narrowed via helper methods
- All DB query results type-checked
- All HTTP response data validated before access
- Property `@var` annotations for range types (`int<1, max>`)
- Constructor `assert()` for runtime range validation

### Rector

Rule sets:
- `TYPE_DECLARATION` — add return types, typed properties
- `CODE_QUALITY` — simplify conditions, remove dead code
- `EARLY_RETURN` — reduce nesting
- `DEAD_CODE` — remove unreachable code

Skipped:
- Facade-to-DI rules (package uses static class pattern)
- Migration refactoring rules

### Laravel Pint

Default Laravel coding style with no custom rules.

### Conventional Commits

| Type | Scope | Example |
|------|-------|---------|
| `feat` | `contracts`, `drivers`, `services`, `data`, `commands` | `feat(drivers): add HTTP embedding driver` |
| `fix` | any | `fix(services): handle empty document array` |
| `refactor` | any | `refactor(contracts): separate contextWindow from maxTokens` |
| `test` | `unit`, `integration`, `arch` | `test(unit): add FixedSizeChunker boundary tests` |
| `docs` | any | `docs: add architecture specification` |
| `chore` | `config`, `ci`, `deps` | `chore(deps): require php 8.4` |

---

## 16. Out of Scope (Future)

These features are intentionally excluded from MVP. The architecture supports them but they are not implemented.

| Feature | Why deferred |
|---------|-------------|
| Multi-provider drivers (Anthropic, Cohere) | Single OpenAI-compatible HTTP covers all |
| Reranking | Basic cosine similarity + BM25 sufficient for MVP. Schema includes `content_tsv` for future hybrid search rank fusion |
| PDF/DOCX/image parsing | Caller territory, not core |
| Multi-tenancy | Can be added via metadata filters |
| Document versioning | Can be added via content_hash tracking |
| Query embedding cache | Stores embeddings in cache for offline query. `Retriever` already separates embed + search — cache can wrap embed step |
| Metrics dashboard | Events provide the foundation. Users can build dashboards from `QueryCompleted`/`IngestionCompleted` events + `Log::info()` |
| Custom chunking strategies (recursive, sentence-based) | Fixed-size sufficient for MVP |
| Query builder (fluent API) | Simple `query()` + `filters` array sufficient for MVP |
| Artisan UI for management | Separate package |
