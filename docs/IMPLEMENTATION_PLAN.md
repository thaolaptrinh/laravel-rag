# Laravel RAG — Implementation Plan

> Based on: docs/ARCHITECTURE.md v1.1.0
> Date: 2026-03-28
> PHP: 8.4+ | Laravel: 11.0+ | PostgreSQL: 13+ with pgvector

---

## Overview

6 phases, 29 tasks total. Each phase produces working, tested code. No phase depends on unfinished work from a previous phase.

```
Phase 1: Foundation (contracts, data, exceptions, config, service provider)
Phase 2: Chunking + Embedding
Phase 3: Vector Storage (pgvector)
Phase 4: Query Pipeline (retrieval + LLM + prompt)
Phase 5: Public API (Rag class, commands, facade)
Phase 6: Polish (AGENTS.md update, README, CI, final validation)
```

---

## Phase 1: Foundation

> Goal: All interfaces, value objects, exceptions, config, service provider. Can be tested with PHPStan and arch tests. No runtime functionality yet.

### Tasks

#### 1.1 Project skeleton setup
- Fix `composer.json` namespace: `Thaolaptrinh\Rag`
- Fix `phpstan.neon.dist`: level 9, include larastan extension
- Fix `rector.php`: PHP 8.4, code quality sets
- Fix `phpunit.xml.dist`: test suite name "Laravel RAG Test Suite"
- Fix `tests/Pest.php`, `tests/TestCase.php`: Orchestra Testbench setup
- Delete skeleton remnants (`configure.php`, `database/factories/ModelFactory.php`)
- Verify: `composer analyse` passes (0 errors), `composer test` passes (0 tests but no crash)

#### 1.2 Domain value objects
- Create `src/Data/Document.php` — readonly, `create()` factory, `contentHash()`
- Create `src/Data/Chunk.php` — readonly, `create()` factory, deterministic ID
- Create `src/Data/QueryResult.php` — readonly, `create()` factory
- Create `src/Data/Answer.php` — readonly, `create()` factory, `@param list<QueryResult>`
- Create `src/Data/IngestionResult.php` — readonly, `create()` factory
- Verify: PHPStan level 9 passes, unit tests for all value objects

#### 1.3 Exception hierarchy
- Create `src/Exceptions/RagException.php` — abstract, extends `\RuntimeException`
- Create `src/Exceptions/EmbeddingFailedException.php` — `create(string, ?Throwable)`
- Create `src/Exceptions/StorageFailedException.php` — `create(string, ?Throwable)`
- Create `src/Exceptions/RetrievalFailedException.php` — `create(string, ?Throwable)`
- Create `src/Exceptions/GenerationFailedException.php` — `create(string, ?Throwable)`
- Create `src/Exceptions/ChunkingFailedException.php` — `create(string, ?Throwable)`
- Create `src/Exceptions/DocumentNotFoundException.php` — `create(string)`
- Create `src/Exceptions/ConfigurationException.php` — `create(string)`
- Verify: PHPStan level 9, unit tests for hierarchy + named constructors

#### 1.4 Pipeline events
- Create `src/Events/DocumentSkipped.php` — `documentId`, `reason`, `traceId`, `createdAt`
- Create `src/Events/DocumentIngested.php` — `documentId`, `chunkCount`, `traceId`, `createdAt`
- Create `src/Events/DocumentIngestionFailed.php` — `documentId`, `reason`, `traceId`, `throwable`, `createdAt`
- Create `src/Events/IngestionCompleted.php` — `ingested`, `skipped`, `errors`, `durationMs`, `traceId`, `createdAt`
- Create `src/Events/QueryCompleted.php` — `question`, `chunksRetrieved`, `durationMs`, `traceId`, `createdAt`
- All events are plain objects (no ShouldQueue) — dispatched synchronously by pipelines
- Verify: PHPStan level 9, unit tests for event properties

#### 1.5 Interface contracts
- Create `src/Contracts/Chunker.php` — `split()`, `getChunkSize()`, `getOverlap()`
- Create `src/Contracts/ContextEnricher.php` — `enrich(Chunk, string $documentContent, array $documentMetadata): Chunk`
- Create `src/Contracts/EmbeddingDriver.php` — `embed()`, `embedBatch()`, `dimensions()`
- Create `src/Contracts/VectorStore.php` — `storeMany()`, `search($queryEmbedding, $topK, $filters, $minScore)`, `getDocumentsByIds()`, `deleteByDocumentId()`, `deleteByDocumentIds()`, `truncate()`
- Create `src/Contracts/Retriever.php` — `retrieve(string $query, int $topK, array $filters)`
- Create `src/Contracts/LlmDriver.php` — `generate()`, `generateWithSystem()`, `generateStream()`, `generateStreamWithSystem()`, `getContextWindow()`, `getMaxOutputTokens()`
- Create `src/Contracts/PromptBuilder.php` — `build()`, `buildWithSystem()`, `estimateTokens()`
- Verify: PHPStan level 9, arch test: contracts namespace contains only interfaces

#### 1.6 Configuration
- Create `config/rag.php` — full structure from ARCHITECTURE.md Section 9
- Create `database/migrations/2026_03_28_000001_create_rag_documents_table.php` — raw SQL, `VARCHAR(255)` PK
- Create `database/migrations/2026_03_28_000002_create_rag_chunks_table.php` — raw SQL, `vector(N)` with dimensions from config, `content_tsv` GENERATED column, constraints
- Create `database/migrations/2026_03_28_000003_create_rag_chunks_hnsw_index.php` — `$withinTransaction = false`, CONCURRENTLY, HNSW + metadata GIN + content_tsv GIN indexes
- All migrations use `protected $connection = 'rag'` to run on PostgreSQL, not app's default DB
- Verify: PHPStan passes on config and migration files

#### 1.7 Service provider + static entry point
- Create `src/RagServiceProvider.php` — mergeConfig, bind all interfaces, publish config + migrations, commands
- Create `src/Rag.php` — static class proxying to container
- Create `src/RagManager.php` — optional, if needed for Manager pattern
- Create `src/Services/RagLogger.php` — structured logging with trace_id, pipeline, step
- Config helper methods: `configString()`, `configInt()`, `configFloat()`, `configArray()` with PHPStan-safe typing
- Verify: PHPStan level 9, service provider resolves all bindings, arch tests pass

#### 1.8 Architecture tests
- Update `tests/ArchTest.php` — contracts only interfaces, services no driver imports, drivers implement contracts, exceptions extend RagException
- Verify: `composer test` passes all arch tests

### Phase 1 Validation

```bash
composer analyse    # 0 errors
composer test       # All pass (arch tests + value object tests + exception tests)
```

---

## Phase 2: Chunking + Embedding

> Goal: Documents can be split into chunks, and text can be embedded via HTTP. No database storage yet.

### Tasks

#### 2.1 FixedSizeChunker
- Create `src/Services/Chunking/FixedSizeChunker.php`
- Implements `Chunker` interface
- Constructor: `int $chunkSize = 1000, int $overlap = 200` with validation (overlap < chunkSize, both > 0)
- `split(Document)`: split by character count, preserve boundaries, inherit document metadata + add `chunk_index`, `chunk_start`, `chunk_end`
- `getChunkSize()`, `getOverlap()` with `@return int<1, max>` / `int<0, max>` + `@var` annotations + `assert()`
- Verify: PHPStan 9, unit tests (short text → 1 chunk, long text → multiple, overlap correctness, metadata inheritance, empty text)

#### 2.2 HttpEmbeddingDriver
- Create `src/Drivers/Embeddings/HttpEmbeddingDriver.php`
- Implements `EmbeddingDriver` interface
- Constructor: `apiKey`, `model`, `dimensions`, `apiUrl`, `batchSize`, `timeout`
- Constructor validation: empty apiKey → throw `ConfigurationException`
- `embed(string)`: delegate to `embedBatch([$text])[0]`
- `embedBatch(array)`: single HTTP POST to `{apiUrl}`, handle batch_size splitting
- Response validation: check `data` array, each item has `embedding` array, `is_array` checks
- `JSON_THROW_ON_ERROR` on all `json_decode`
- `Http::withToken()->timeout()->connectTimeout()->acceptJson()->post()`
- Retry: 429 (3 retries, exponential backoff), 500/502/503 (2 retries), 401/403 (no retry)
- Verify: PHPStan 9, unit tests with `Http::fake()` (success, 401, 429, 500, batch split, empty input)

### Phase 2 Validation

```bash
composer analyse    # 0 errors
composer test       # All Phase 1 + Phase 2 tests pass
```

---

## Phase 3: Vector Storage (pgvector)

> Goal: Chunks with embeddings can be stored, searched, and deleted in PostgreSQL with pgvector.

### Tasks

#### 3.1 PgVectorStore
- Create `src/Drivers/VectorStores/PgVectorStore.php`
- Implements `VectorStore` interface
- Constructor: `string $connection`, `string $documentsTable`, `string $chunksTable`
- `storeMany(array $items)`: single bulk `INSERT ... ON CONFLICT (document_id, chunk_index) DO UPDATE`
- `search(array $queryEmbedding, int $topK, array $filters, float $minScore)`: raw SQL with `<=>`, `SET LOCAL hnsw.ef_search`, metadata WHERE clauses, minScore threshold
- Schema includes `content_tsv TSVECTOR GENERATED ALWAYS AS (to_tsvector('english', content)) STORED` for future hybrid search
- `deleteByDocumentId(string $id)`: hard delete from chunks table
- `deleteByDocumentIds(array $ids)`: hard delete with `whereIn`
- Error handling: catch `QueryException`, wrap in `StorageFailedException`
- `JSON_THROW_ON_ERROR` on metadata encode/decode
- Verify: PHPStan 9, unit tests (mock DB via `Http::fake()` pattern — need real PG for full test)

#### 3.2 SimilarityRetriever
- Create `src/Services/Retrieving/SimilarityRetriever.php`
- Implements `Retriever` interface
- Constructor: `EmbeddingDriver $embedder`, `VectorStore $store`, `float $minScore`
- `retrieve(string $query, int $topK, array $filters)`: embed query → call `store->search()` with minScore
- Verify: PHPStan 9, unit tests with mocked embedder + store

#### 3.3 PgVector integration test (CI only)
- Create `tests/Integration/PgVectorStoreTest.php`
- Requires real PostgreSQL with pgvector — run only in CI with pgvector service container
- Tests: storeMany + search + delete + ON CONFLICT idempotency + metadata filter
- Verify: CI pipeline passes

### Phase 3 Validation

```bash
composer analyse    # 0 errors
composer test       # All Phase 1 + 2 + 3 tests pass
```

---

## Phase 4: Query Pipeline

> Goal: End-to-end query works — ask question, get answer with sources.

### Tasks

#### 4.1 SimplePromptBuilder
- Create `src/Services/Prompt/SimplePromptBuilder.php`
- Implements `PromptBuilder` interface
- `estimateTokens(string)`: `ceil(strlen($text) × tokens_per_char)`, `max(0, ...)`
- `build(array $context, string $question, int $maxContextTokens)`: build context from chunks, truncate to fit token budget
- `buildWithSystem(...)`: prepend system message
- Prompt format: `System: ...\n\nContext:\n...\n\nQuestion: ...\n\nAnswer:`
- Verify: PHPStan 9, unit tests (token estimation, truncation, empty context, system prompt)

#### 4.2 HttpLlmDriver
- Create `src/Drivers/Llm/HttpLlmDriver.php`
- Implements `LlmDriver` interface
- Constructor: `apiKey`, `model`, `maxOutputTokens`, `contextWindow`, `temperature`, `apiUrl`, `timeout`
- Constructor validation: empty apiKey → throw `ConfigurationException`
- `generate(string)`: delegate to `generateWithSystem(default, $prompt)`
- `generateWithSystem(string, string)`: HTTP POST with messages array
- `generateStream(string, callable)`: parse SSE, call callback per token
- `generateStreamWithSystem(string, string, callable)`: SSE with system message
- `JSON_THROW_ON_ERROR` on stream json_decode, catch `\JsonException`, log warning, skip
- `str_starts_with()` for SSE `data: ` detection
- Retry policy: same as HttpEmbeddingDriver
- Response validation: check `choices[0].message.content` is_string
- Verify: PHPStan 9, unit tests with `Http::fake()`

#### 4.3 LlmContextEnricher
- Create `src/Drivers/Enrichment/LlmContextEnricher.php`
- Implements `ContextEnricher` interface
- Constructor: `LlmDriver $llm` (uses already-bound LlmDriver)
- `enrich(Chunk, string $documentContent, array $documentMetadata)`: call LLM with system prompt to generate 50-100 token context, prepend to chunk content, return new Chunk
- Uses `generateWithSystem()` with Anthropic-proven prompt pattern
- Returns new `Chunk` (immutable — original unchanged)
- Verify: PHPStan 9, unit test with mocked LlmDriver

#### 4.4 IngestionPipeline
- Create `src/Services/IngestionPipeline.php`
- Constructor: `Chunker`, `EmbeddingDriver`, `VectorStore`, `RagLogger` (all interfaces, readonly)
- **Optional `ContextEnricher`**: Nullable constructor param. When `config('rag.contextual.enabled')` and enricher is bound, call `enrich()` between chunk and embed steps
- **Document size validation** (step 0): Check `strlen($content) ≤ max_content_length` for ALL documents before any API call. Throw `ChunkingFailedException` immediately for oversized documents
- **Content hash check** (step 1): Batch `SELECT id, content_hash FROM rag_documents WHERE id IN (...)`. Partition into `[skip]` and `[process]`. Dispatch `DocumentSkipped` event
- **Sub-batch processing** (step 2): Split `[process]` into groups of `sub_batch_size` (default 10). For each sub-batch: chunk → enrich (if enabled) → embed → store → free memory → next
- **Concurrent ingestion safety**: Per-document `pg_try_advisory_lock()` + hash re-check inside transaction. If unchanged after lock → skip (discard embeddings). `pg_advisory_unlock()` always in finally block
- **Transaction boundaries**: Each document's upsert + DELETE old chunks + INSERT new chunks in a single `DB::transaction()`. On failure → rollback — no orphaned documents
- **Pipeline timeout**: Stopwatch between sub-batches. If elapsed > `pipeline_timeout` (default 600s) → throw `ChunkingFailedException` with progress
- **Error isolation**: Per-document try/catch inside sub-batch. Failed doc → log error, dispatch `DocumentIngestionFailed`, increment counter, continue to next document
- **Events**: Dispatch `DocumentIngested` per document, `IngestionCompleted` at end
- Returns `IngestionResult` with ingested/skipped/errors counts + traceId
- Verify: PHPStan 9, unit tests (mock drivers, verify sub-batch processing, hash skip, advisory lock, error counting, timeout, events)

#### 4.5 QueryPipeline
- Create `src/Services/QueryPipeline.php`
- Constructor: `Retriever`, `PromptBuilder`, `LlmDriver`, `RagLogger` (all interfaces, readonly)
- `query(string $question, array $options)`: retrieve → build prompt → generate
- `queryStream(string $question, callable $callback, array $options)`: retrieve → build → stream generate
- Uses `LlmDriver::getContextWindow()` for token budget
- Options: `top_k`, `filters`, `system` (override default)
- Returns `Answer` with text, sources, traceId
- Dispatches `QueryCompleted` event on success
- Error handling: throw — caller decides
- Verify: PHPStan 9, unit tests (mock all interfaces)

#### 4.6 Pipeline integration tests
- Create `tests/Integration/IngestionPipelineTest.php` — mock VectorStore + EmbeddingDriver
- Create `tests/Integration/QueryPipelineTest.php` — mock all drivers
- Verify: `composer test` passes

### Phase 4 Validation

```bash
composer analyse    # 0 errors
composer test       # All tests pass (including pipeline integration)
```

---

## Phase 5: Public API

> Goal: User-friendly public API via static `Rag` class, Artisan commands.

### Tasks

#### 5.1 Static Rag class + RagManager
- Update `src/Rag.php` — add all public API methods: `ingest()`, `ingestMany()`, `query()`, `queryStream()`, `delete()`, `deleteMany()`, `truncate()`, `ingestQueued()`, `ingestManyQueued()`, `fake()`
- Each method resolves pipeline from container and delegates
- `query()` returns `Answer`
- `queryStream()` accepts callback, returns `Answer`
- `delete()` throws `DocumentNotFoundException` if not found
- `deleteMany()` returns count
- `ingestQueued(Document, ?string $queue)` — dispatches `RagIngestJob` per document
- `ingestManyQueued(array, ?string $queue)` — dispatches one job per document
- `fake()` — swaps container binding with `FakeRagManager`
- Verify: PHPStan 9, feature tests

#### 5.2 Artisan commands
- Create `src/Commands/RagIngestCommand.php` — `php artisan rag:ingest {content} --id= --metadata=`
- Create `src/Commands/RagQueryCommand.php` — `php artisan rag:query {question} --top-k= --filters=`
- Create `src/Commands/RagDeleteCommand.php` — `php artisan rag:delete {id}` or `--all`
- Create `src/Commands/RagInstallCommand.php` — publish config + migrations, create extension, run migrations
- Create `src/Commands/RagIndexCommand.php` — `php artisan rag:index` creates HNSW index after data ingestion
- Commands use dependency injection (not Facade)
- Verify: PHPStan 9, feature tests for each command

#### 5.3 Queue support
- Create `src/Jobs/RagIngestJob.php` — implements `ShouldQueue`
- Serialized properties: `documentId`, `documentContent`, `documentMetadata` (plain values, no object serialization)
- `handle(IngestionPipeline $pipeline)`: calls `$pipeline->ingest()` with reconstructed `Document`
- `retry_until`: based on `config('rag.ingestion.pipeline_timeout')`
- Failed jobs → dispatches `DocumentIngestionFailed` event
- Verify: PHPStan 9, unit test `tests/Unit/Jobs/RagIngestJobTest.php`

#### 5.4 Testing helpers
- Create `src/Testing/FakeRagManager.php` — replaces `RagManager` in container during tests
  - Records all method calls (ingest, query, delete, etc.) with arguments
  - Returns deterministic `IngestionResult` / `Answer` objects
  - `assertIngested(string $documentId)`: checks if document was ingested
  - `assertQueried(string $question)`: checks if question was queried
  - `assertDeleted(string $documentId)`: checks if document was deleted
  - Auto-reset in PHPUnit `tearDown`
- Create `src/Testing/InMemoryVectorStore.php` — implements `VectorStore` for user integration tests without pgvector
  - Stores in PHP arrays, search via cosine similarity calculation
- Verify: PHPStan 9, unit tests for fake behavior + assertions

#### 5.5 Service provider final wiring
- Update `src/RagServiceProvider.php` — bind all concrete implementations:
  - `Chunker::class` → `FixedSizeChunker`
  - `ContextEnricher::class` → `LlmContextEnricher` (only if `config('rag.contextual.enabled')`)
  - `EmbeddingDriver::class` → `HttpEmbeddingDriver`
  - `VectorStore::class` → `PgVectorStore`
  - `Retriever::class` → `SimilarityRetriever`
  - `PromptBuilder::class` → `SimplePromptBuilder`
  - `LlmDriver::class` → `HttpLlmDriver`
  - `IngestionPipeline::class` → singleton
  - `QueryPipeline::class` → singleton
  - `'rag'` → singleton `RagManager`
- Config validation at boot: throw `ConfigurationException` if:
  - `rag.embedding.api_key` is empty
  - `rag.llm.api_key` is empty
  - Database connection 'rag' not configured
  - rag.embedding.dimensions < 1
  - rag.chunking.overlap >= rag.chunking.chunk_size
- Verify: PHPStan 9, integration test that service provider validates config correctly

#### 5.6 Feature tests for public API
- Create `tests/Feature/RagApiTest.php` — test all `Rag::*` methods including `fake()`, `ingestQueued()`
- Verify: `composer test` passes

### Phase 5 Validation

```bash
composer analyse    # 0 errors
composer test       # All tests pass (including feature + command tests)
```

---

## Phase 6: Polish

> Goal: Documentation, CI, final validation. Ready to ship.

### Tasks

#### 6.1 Update AGENTS.md
- Verify rules match finalized architecture
- Remove any stale references

#### 6.2 README.md
- Package overview, installation instructions
- Quick start guide with code examples
- Configuration reference (all env vars)
- Architecture overview (link to ARCHITECTURE.md)
- API reference (all public methods)

#### 6.3 CI workflow updates
- Create `.github/workflows/run-tests.yml`:
  - Triggers: push to main, pull_request
  - PHP: 8.4
  - Services: postgres (pgvector/pgvector:pg16) with env vars
  - Steps: composer install → copy .env.example → php artisan test:migrate → composer test → composer analyse
- Create `.github/workflows/phpstan.yml`:
  - Triggers: push to main, pull_request
  - PHP: 8.4
  - Steps: composer install → composer analyse
- Create `.env.example` — all `RAG_*` env vars from ARCHITECTURE.md Section 9 (with placeholder values)
- Verify `composer.json` includes Laravel 13 support (`^13.0`)
- Verify: CI passes on GitHub Actions (requires pgvector service)

#### 6.4 Final validation
- Run `composer analyse` — 0 errors
- Run `composer test` — all pass
- Run `vendor/bin/rector process --dry-run` — 0 changes
- Run `vendor/bin/pint --test` — no style issues
- Cross-check: every interface method has an implementation, every config key is used, every exception is thrown somewhere

---

## Task Dependencies (DAG)

```
Phase 1
  1.1 ─→ 1.2, 1.3, 1.4
  1.2 ─→ (tested in 1.8)
  1.3 ─→ (tested in 1.8)
  1.4 ─→ (tested in 1.8)
  1.5 ─→ (tested in 1.8)
  1.6 ─→ 1.7
  1.7 ─→ 1.8
  1.8 ── validation

Phase 2 (requires Phase 1)
  1.8 ─→ 2.1, 2.2
  2.1 ─→ (tested in Phase 2 validation)
  2.2 ── Phase 2 validation

Phase 3 (requires Phase 2)
  2.2 ─→ 3.1, 3.2
  3.1 ─→ 3.3
  3.2 ── (tested in Phase 3 validation)
  3.3 ── Phase 3 validation

Phase 4 (requires Phase 3)
  1.8 ─→ 4.1, 4.2, 4.3, 4.4, 4.5
  3.2 ─→ 4.5 (SimilarityRetriever used in QueryPipeline)
  3.1 ─→ 4.4 (storeMany used in IngestionPipeline)
  4.1 ─→ 4.5 (PromptBuilder used in QueryPipeline)
  4.2 ─→ 4.3 (LlmDriver used by LlmContextEnricher), 4.5 (LlmDriver used in QueryPipeline)
  4.3 ─→ 4.4 (ContextEnricher optional in IngestionPipeline)
  4.4 ─→ 4.6
  4.5 ─→ 4.6
  4.6 ── Phase 4 validation

Phase 5 (requires Phase 4)
  4.5 ─→ 5.1, 5.2, 5.3, 5.4, 5.5
  5.1 ─→ 5.6
  5.2 ─→ 5.5, 5.6
  5.3 ─→ 5.5
  5.4 ─→ 5.5
  5.5 ─→ 5.6
  5.6 ── Phase 5 validation

Phase 6 (requires Phase 5)
  5.6 ─→ 6.1, 6.2, 6.3, 6.4
```

---

## Commit Strategy

Each phase gets a single squashed commit:

```
feat(contracts): add core interfaces, value objects, and exception hierarchy
feat(chunking): add FixedSizeChunker with boundary and overlap handling
feat(embedding): add HTTP embedding driver with batch support and retry logic
feat(vector-store): add PgVectorStore with idempotent upsert and similarity search
feat(query): add query pipeline with retrieval, prompt building, and LLM generation
feat(api): add public Rag API, Artisan commands, and service provider wiring
docs: update AGENTS.md, README, and CI configuration
```

---

## Estimated Effort

| Phase | Tasks | Complexity | Est. Time |
|-------|-------|-----------|----------|
| 1. Foundation | 8 | Low-Medium | 2-3h |
| 2. Chunking + Embedding | 2 | Medium | 1-2h |
| 3. Vector Storage | 3 | Medium-High | 2-3h |
| 4. Query Pipeline | 6 | Medium-High | 3-4h |
| 5. Public API | 6 | Medium | 2-3h |
| 6. Polish | 4 | Low | 1h |
| **Total** | **29** | | **11-16h** |

> Note: Phase 3 includes real PostgreSQL testing which may take longer to set up. Phase 6 is documentation-heavy.
