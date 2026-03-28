---
description: "Phase 1 — Foundation: contracts, value objects, exceptions, config, service provider"
subtask: true
---

Implement Phase 1: Foundation

Read @docs/IMPLEMENTATION_PLAN.md for the full task list (Phase 1 section).
Read @docs/ARCHITECTURE.md for all interface signatures, domain object shapes, config structure, DB schema, and exception hierarchy.

## Tasks (in order)

### 1.1 Project skeleton setup
- Fix `composer.json`: namespace `Thaolaptrinh\Rag`, provider `Thaolaptrinh\Rag\RagServiceProvider`
- Fix `phpstan.neon.dist`: level 9, include `vendor/larastan/larastan/bootstrap.php`, remove `phpstan-baseline.neon`
- Fix `rector.php`: `->withPhpSets(php84: true)`
- Fix `phpunit.xml.dist`: suite name "Laravel RAG Test Suite"
- Fix `tests/Pest.php`: namespace `Thaolaptrinh\Rag\Tests`
- Fix `tests/TestCase.php`: namespace `Thaolaptrinh\Rag\Tests`, remove Eloquent Factory boilerplate
- Delete: `src/Skeleton.php`, `src/SkeletonServiceProvider.php`, `src/Facades/`, `src/Commands/SkeletonCommand.php`, `database/factories/`, `config/skeleton.php`, `tests/ExampleTest.php`

### 1.2 Domain value objects
- `src/Data/Document.php` — `final readonly class`, private constructor, `static create()`, `contentHash(): string`
- `src/Data/Chunk.php` — `final readonly class`, `static create()`, deterministic ID `{documentId}::chunk::{index}`
- `src/Data/QueryResult.php` — `final readonly class`, `static create()`
- `src/Data/Answer.php` — `final readonly class`, `static create()`, `@param list<QueryResult>`
- `src/Data/IngestionResult.php` — `final readonly class`, `static create()`

### 1.3 Exception hierarchy
- `src/Exceptions/RagException.php` — abstract, extends `\RuntimeException`
- 7 concrete exceptions with `static create(string, ?Throwable)` named constructors

### 1.4 Interface contracts
- `src/Contracts/Chunker.php`
- `src/Contracts/EmbeddingDriver.php`
- `src/Contracts/VectorStore.php`
- `src/Contracts/Retriever.php`
- `src/Contracts/LlmDriver.php`
- `src/Contracts/PromptBuilder.php`

### 1.5 Configuration + Migrations
- `config/rag.php` — full structure from ARCHITECTURE.md Section 9
- 3 migration files — `rag_documents` (VARCHAR PK), `rag_chunks` (UUID PK + vector), HNSW index

### 1.6 Service provider + static entry point
- `src/RagServiceProvider.php` — mergeConfig, bind interfaces, publish
- `src/Rag.php` — static proxy class

### 1.7 Architecture tests
- Update `tests/ArchTest.php`

## Validation after each task
Run `composer analyse` — must be 0 errors.
Run `composer test` — must pass.

## Critical rules (AGENTS.md)
- `declare(strict_types=1)` on every PHP file
- `final readonly class` + private constructor + `static create()` for domain objects
- No `@phpstan-ignore` — fix the actual type problem
- PHPStan level 9 — zero errors
