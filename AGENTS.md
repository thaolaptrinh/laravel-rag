# Laravel RAG - Agent Rules

This document defines the global system rules for the Laravel RAG package development.

**Authoritative source**: `docs/ARCHITECTURE.md` — if AGENTS.md and ARCHITECTURE.md conflict, ARCHITECTURE.md wins.

## Architecture Rules

### Pipeline-Based Design
- **Ingestion Pipeline**: Validate sizes → Check hash → Chunk (sub-batch) → Embed (batch) → Store (batch)
- **Query Pipeline**: Retrieve (embeds internally) → Build Prompt → Generate
- **No mixing**: Ingestion and Query pipelines are completely independent
- **Independence**: Each pipeline can be tested, deployed, and scaled independently
- **Memory safety**: Ingestion processes documents in sub-batches — never all at once
- **Concurrent safety**: Per-document advisory lock + hash re-check inside transaction
- **Events**: Pipelines dispatch events — drivers do NOT dispatch events

### Separation of Concerns
- **Core Domain**: `Data/` value objects + `Contracts/` interfaces — no external API calls
- **Service Layer**: `Services/` business logic only — depends on contracts, never on concrete drivers
- **Driver Layer**: `Drivers/` — all provider-specific HTTP and DB code lives here
- **Configuration**: Provider selection via `config/rag.php` only

### No Tight Coupling
- Services depend on interfaces (`Contracts\EmbeddingDriver`), never on concrete classes (`Drivers\Embeddings\HttpEmbeddingDriver`)
- Use `readonly` constructor injection throughout
- `Rag` static class proxies to container-resolved pipelines — no direct service access

## Dependency Rules

### No Direct External APIs in Core
- **Core Services** (`IngestionPipeline`, `QueryPipeline`): MUST NOT call HTTP APIs or DB directly
- **All Integrations**: MUST go through driver interfaces (`EmbeddingDriver`, `VectorStore`, `LlmDriver`)
- **Service Boundaries**: Pipelines are provider-agnostic — they don't know about OpenAI, pgvector, etc.

### Driver Abstraction
- **EmbeddingDriver**: Interface for all embedding providers (HTTP-based, OpenAI-compatible)
- **VectorStore**: Interface for all vector databases (store, search, delete)
- **LlmDriver**: Interface for all LLM providers (generate, stream, system message)
- **Retriever**: Interface for all retrieval strategies (receives raw query string)
- **Chunker**: Interface for all text splitting strategies
- **ContextEnricher**: Interface for optional chunk enrichment (Contextual Retrieval)
- **PromptBuilder**: Interface for prompt construction with token limits

### No Vendor SDKs
- ALL HTTP calls via `Illuminate\Support\Facades\Http` — no `openai-php`, no `pinecone-php`
- Any OpenAI-compatible API (OpenAI, FPT Cloud, Ollama, vLLM, Supabase) works via config only

### Configuration-Driven Provider Selection
- API URLs, keys, models all come from `config/rag.php` with `RAG_*` env prefix
- Service provider binds concrete implementations based on config at registration time
- Empty API key → throw `ConfigurationException` at startup (not runtime `EmbeddingFailedException`)

## Code Quality Rules

### Strict Typing (NON-NEGOTIABLE)
- **Every PHP file**: MUST start with `declare(strict_types=1);`
- **All methods**: MUST have explicit parameter types AND return types
- **No mixed types**: Use specific array shapes in PHPDoc (`list<Chunk>`, `list<float>`, etc.)
- **Readonly properties**: All constructor dependencies MUST use `private readonly`
- **Domain objects**: All use `final readonly class` with private constructors + static `create()` factory

### PHPStan Level 9 (NON-NEGOTIABLE)
- Zero errors, zero warnings, zero `@phpstan-ignore`
- Config values narrowed via typed helper methods (`configString()`, `configInt()`, `configFloat()`, `configArray()`)
- DB query results type-checked with `is_array()`, `is_string()` guards
- HTTP response data validated with `is_array()` + `isset()` before access
- Property `@var` annotations for range types (`int<1, max>`, `int<0, max>`)
- Constructor `assert()` for runtime range validation (e.g., `assert($maxTokens >= 1)`)
- `list<T>` syntax for typed arrays (not bare `array`)

### Production Best Practices
- **Document size validation**: Check `strlen($content) ≤ max_content_length` before any API call
- **Sub-batch processing**: Ingestion processes N documents at a time (`sub_batch_size`, default 10)
- **Pipeline timeout**: Configurable overall timeout (`pipeline_timeout`, default 600s), checked between sub-batches
- **Transaction boundaries**: Per-document upsert + chunk replacement in a single DB transaction
- **Error isolation**: Per-document try/catch — one failure does not abort the entire ingestion
- **Batch operations in ingestion**: `embedBatch()`, `storeMany()` — no single-item loops
- **Single embed is fine for query-time**: `embed()` for one query string is acceptable
- **JSON_THROW_ON_ERROR**: Required for ALL `json_decode()` calls — no exceptions
- **Custom exceptions**: All extend `RagException` with `static create(string, ?\Throwable)` named constructors
- **Exception chaining**: Always pass `$previous` to maintain full stack trace
- **Structured logging**: All logs include `trace_id`, `pipeline`, `step`
- **Hard delete**: No soft delete — chunks are derived data, soft delete bloats HNSW index
- **Content hash optimization**: Skip re-embed if document content unchanged
- **HTTP timeouts**: Configure explicitly (default 120s) + connect timeout (5s)
- **Retry policy**: 429 (3 retries, exponential backoff), 5xx (2 retries), 401/403 (no retry)
- **Malformed SSE**: Log warning, skip silently — don't break the stream

### Rector
- Sets: `TYPE_DECLARATION`, `CODE_QUALITY`, `EARLY_RETURN`, `DEAD_CODE`
- Skip: Facade-to-DI rules, migration refactoring rules
- PHP 8.4: `->withPhpSets(php84: true)`

## Development Rules

### Phase-Based Development
Follow the 6-phase order in `docs/IMPLEMENTATION_PLAN.md`:

1. **Phase 1**: Foundation — contracts, value objects, exceptions, config, service provider
2. **Phase 2**: Chunking + Embedding — FixedSizeChunker, HttpEmbeddingDriver
3. **Phase 3**: Vector Storage — PgVectorStore, SimilarityRetriever
4. **Phase 4**: Query Pipeline — PromptBuilder, HttpLlmDriver, Ingestion/QueryPipeline
5. **Phase 5**: Public API — Rag static class, Artisan commands, wiring
6. **Phase 6**: Polish — docs, CI, final validation

### Validation Gates
- **Never skip**: `composer analyse` after each phase — 0 errors
- **Never skip**: `composer test` — all tests pass
- **Never skip**: Architecture validation — no provider lock-in

### Domain Model Rules
- `Document.id` is user-provided (auto-UUID if null) — becomes `rag_documents` primary key directly
- `Chunk.id` is deterministic composite: `{documentId}::chunk::{index}`
- Embeddings are NOT on domain objects — they exist only inside pipeline + vector store
- `IngestionResult`, `Answer`, `QueryResult` — all use private constructor + `create()` factory

### Database Rules
- Separate `rag` database connection with `RAG_DB_*` env prefix
- Two tables: `rag_documents` (user-provided VARCHAR PK), `rag_chunks` (UUID PK, FK to documents)
- `chunk_index` is `INTEGER` (not SMALLINT) — avoids 32K limit
- HNSW index with `vector_cosine_ops`, `m=16`, `ef_construction=64`
- Table names are hardcoded (`rag_documents`, `rag_chunks`) — no prefix customization

### API Rules
- `Rag::ingest()` / `Rag::ingestMany()` — returns `IngestionResult`
- `Rag::query()` — returns `Answer`
- `Rag::queryStream()` — streams tokens via callback, returns `Answer`
- `Rag::delete($id)` — throws `DocumentNotFoundException` if not found, returns `bool`
- `Rag::deleteMany($ids)` — returns deleted count
- `Rag::truncate()` — deletes all documents and chunks
- No Facade — static `Rag` class (Scout/Horizon pattern)

## Critical Success Factors

### Must Have
- ✅ Provider-agnostic architecture (core never mentions providers)
- ✅ Batch operations for ingestion (`embedBatch`, `storeMany`)
- ✅ Idempotent ingestion (content hash check + ON CONFLICT)
- ✅ Structured logging with trace IDs
- ✅ PHPStan level 9 — zero errors
- ✅ Immutable domain objects with private constructors
- ✅ Separate database connection (`RAG_DB_*`)

### Must Not Have
- ❌ Provider code in core services
- ❌ Direct API calls in pipelines
- ❌ `@phpstan-ignore` comments
- ❌ `\RuntimeException` or `\InvalidArgumentException` in package code
- ❌ Soft-deleted chunks (bloats HNSW index)
- ❌ `Data` objects with `embedding` field

## Git Conventions

### Conventional Commits (REQUIRED)
Format: `type(scope): description`

| Type | Scope | Example |
|------|-------|---------|
| `feat` | `contracts`, `drivers`, `services`, `data`, `commands` | `feat(drivers): add HTTP embedding driver with batch support` |
| `fix` | any | `fix(services): handle empty document array` |
| `refactor` | any | `refactor(contracts): add Retriever interface` |
| `test` | `unit`, `integration`, `arch`, `feature` | `test(unit): add FixedSizeChunker boundary tests` |
| `docs` | any | `docs: add architecture specification` |
| `chore` | `config`, `ci`, `deps` | `chore(deps): require php 8.4` |

### Commit Rules
- One logical change per commit (squash per phase is acceptable)
- Always run `composer analyse` before committing — 0 errors
- Always run `composer test` before pushing — all pass
- Never commit `.env`, `vendor/`, `build/`

## Response Preferences

- **Code**: Always provide full implementation when coding, never truncate
- **Errors**: Explain root cause before proposing a fix
- **PHPStan**: Never suggest `@phpstan-ignore` — fix the actual problem
- **Brevity**: Do not over-explain things that are obvious from the code

## When in Doubt

- **Question**: Should I add this feature?
  - **Answer**: Is it needed for MVP? If no, defer it.

- **Question**: Should I use this provider?
  - **Answer**: Is there an interface for it? If no, create interface first.

- **Question**: Should I skip PHPStan validation?
  - **Answer**: NEVER. Fix all errors before proceeding.

- **Question**: Should I add a shortcut?
  - **Answer**: NO. Batch operations are required for production performance.

---

**Remember**: These rules ensure the package remains maintainable, extensible, and production-ready. Follow them strictly.
