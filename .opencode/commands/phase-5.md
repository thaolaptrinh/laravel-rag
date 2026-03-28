---
description: "Phase 5 — Public API: Rag static class, Artisan commands, service provider wiring"
subtask: true
---

Implement Phase 5: Public API

Read @docs/IMPLEMENTATION_PLAN.md (Phase 5 section) for full task details.
Read @docs/ARCHITECTURE.md for API signatures.

## Prerequisites
Phase 4 must be complete. Run `composer analyse` and `composer test` to verify.

## Tasks

### 5.1 Static Rag class
- File: `src/Rag.php` — update with all public API methods
- `ingest(Document)`: returns `IngestionResult`
- `ingestMany(array)`: returns `IngestionResult`
- `query(string)`: returns `Answer`
- `queryStream(string, callable)`: returns `Answer`
- `delete(string)`: throws `DocumentNotFoundException`
- `deleteMany(array)`: returns int (deleted count)
- `truncate()`: void
- Each method resolves pipeline from container

### 5.2 Artisan commands
- `src/Commands/RagIngestCommand.php` — `php artisan rag:ingest`
- `src/Commands/RagQueryCommand.php` — `php artisan rag:query`
- `src/Commands/RagDeleteCommand.php` — `php artisan rag:delete`
- `src/Commands/RagInstallCommand.php` — publish config + migrations, create extension

### 5.3 Service provider final wiring
- Bind all concrete implementations to interfaces
- Config validation at boot: throw `ConfigurationException` early on empty API keys

### 5.4 Feature tests for public API

## Validation
Run `composer analyse` — 0 errors.
Run `composer test` — all pass.
