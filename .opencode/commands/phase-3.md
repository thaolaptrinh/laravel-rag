---
description: "Phase 3 — Vector Storage: PgVectorStore, SimilarityRetriever"
subtask: true
---

Implement Phase 3: Vector Storage (pgvector)

Read @docs/IMPLEMENTATION_PLAN.md (Phase 3 section) for full task details.
Read @docs/ARCHITECTURE.md for interface signatures and DB schema specs.

## Prerequisites
Phase 2 must be complete. Run `composer analyse` and `composer test` to verify.

## Tasks

### 3.1 PgVectorStore
- File: `src/Drivers/VectorStores/PgVectorStore.php`
- Implements: `Contracts\VectorStore`
- Constructor: `private readonly string $connection`, table names from config
- `storeMany(array)`: single bulk `INSERT ... ON CONFLICT (document_id, chunk_index) DO UPDATE`
- `search(array $embedding, int $topK, array $filters)`: raw SQL with `<=>` cosine distance, `SET LOCAL hnsw.ef_search`, metadata WHERE
- `deleteByDocumentId(string)`: hard delete
- `deleteByDocumentIds(array)`: hard delete with whereIn
- Error handling: catch `QueryException` → wrap in `StorageFailedException`
- `JSON_THROW_ON_ERROR` on metadata encode/decode

### 3.2 SimilarityRetriever
- File: `src/Services/Retrieving/SimilarityRetriever.php`
- Implements: `Contracts\Retriever`
- Constructor: `private readonly EmbeddingDriver $embedder, private readonly VectorStore $store`
- `retrieve(string $query, int $topK, array $filters)`: embed query → store->search()

### 3.3 Integration test (CI only)
- File: `tests/Integration/PgVectorStoreTest.php`
- Requires real PostgreSQL with pgvector — CI-only with service container
- Tests: storeMany + search + delete + ON CONFLICT + metadata filter

## Validation
Run `composer analyse` — 0 errors.
Run `composer test` — all pass.
