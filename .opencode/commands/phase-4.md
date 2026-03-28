---
description: "Phase 4 — Query Pipeline: PromptBuilder, LlmDriver, IngestionPipeline, QueryPipeline"
subtask: true
---

Implement Phase 4: Query Pipeline

Read @docs/IMPLEMENTATION_PLAN.md (Phase 4 section) for full task details.
Read @docs/ARCHITECTURE.md for interface signatures and pipeline specs.

## Prerequisites
Phase 3 must be complete. Run `composer analyse` and `composer test` to verify.

## Tasks

### 4.1 SimplePromptBuilder
- File: `src/Services/Prompt/SimplePromptBuilder.php`
- Implements: `Contracts\PromptBuilder`
- Token estimation: `ceil(strlen($text) * 0.25)` for English
- `build()`: context from chunks → truncate to token budget
- `buildWithSystem()`: prepend system message

### 4.2 HttpLlmDriver
- File: `src/Drivers/Llm/HttpLlmDriver.php`
- Implements: `Contracts\LlmDriver`
- Empty apiKey → `ConfigurationException`
- `generate()`, `generateWithSystem()`, `generateStream()`, `generateStreamWithSystem()`
- SSE parsing: `str_starts_with()` for `data: `, `JSON_THROW_ON_ERROR`, malformed → log warning + skip
- Retry: same policy as HttpEmbeddingDriver
- Response validation: check `choices[0].message.content` is_string

### 4.3 IngestionPipeline
- File: `src/Services/IngestionPipeline.php`
- Constructor: all interfaces (Chunker, EmbeddingDriver, VectorStore, RagLogger) — `private readonly`
- `ingest(Document)`: hash check → chunk → embed → store
- `ingestMany(array)`: batch with hash optimization, partition, upsert
- Per-document error handling: try/catch, log, continue

### 4.4 QueryPipeline
- File: `src/Services/QueryPipeline.php`
- Constructor: all interfaces (Retriever, PromptBuilder, LlmDriver, RagLogger) — `private readonly`
- `query()`: retrieve → build prompt → generate → return Answer
- `queryStream()`: retrieve → build → stream generate → return Answer

### 4.5 RagLogger
- File: `src/Services/RagLogger.php`
- Structured logging: trace_id, pipeline, step, document_id, duration_ms

### 4.6 Pipeline integration tests

## Validation
Run `composer analyse` — 0 errors.
Run `composer test` — all pass.
