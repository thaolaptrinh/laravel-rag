---
description: "Phase 2 — Chunking + Embedding: FixedSizeChunker, HttpEmbeddingDriver"
subtask: true
---

Implement Phase 2: Chunking + Embedding

Read @docs/IMPLEMENTATION_PLAN.md (Phase 2 section) for full task details.
Read @docs/ARCHITECTURE.md for interface signatures and implementation specs.

## Prerequisites
Phase 1 must be complete. Run `composer analyse` and `composer test` to verify.

## Tasks

### 2.1 FixedSizeChunker
- File: `src/Services/Chunking/FixedSizeChunker.php`
- Implements: `Contracts\Chunker`
- Constructor: `private readonly int $chunkSize = 1000, private readonly int $overlap = 200`
- Validation: `assert($overlap < $chunkSize)`, `assert($chunkSize >= 1)`, `assert($overlap >= 0)`
- `split(Document)`: split by character count, preserve word boundaries, inherit metadata + add chunk_index/chunk_start/chunk_end
- Return type: `list<Chunk>`
- `@return int<1, max>` for getChunkSize(), `@return int<0, max>` for getOverlap()

### 2.2 HttpEmbeddingDriver
- File: `src/Drivers/Embeddings/HttpEmbeddingDriver.php`
- Implements: `Contracts\EmbeddingDriver`
- Constructor: all from config, empty apiKey → `ConfigurationException`
- `embed(string)`: delegate to `embedBatch([$text])[0]`
- `embedBatch(array)`: HTTP POST to apiUrl, batch_size splitting, `JSON_THROW_ON_ERROR`
- Retry: 429 (3 retries, exponential backoff), 5xx (2 retries), 401/403 (no retry)
- HTTP: `Http::withToken()->timeout(120)->connectTimeout(5)->acceptJson()->post()`
- Response validation: `is_array($data)`, `isset($data['data'])`, each item has `embedding` array

## Validation after each task
Run `composer analyse` — 0 errors.
Run `composer test` — all pass.
