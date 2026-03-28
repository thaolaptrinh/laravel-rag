---
description: Review code changes before committing
subtask: true
---

Review all changes against AGENTS.md rules:

1. Run: !`git diff HEAD 2>&1`
2. Check each file:
   - `declare(strict_types=1)` on every PHP file
   - Explicit return types on all methods
   - `private readonly` for constructor dependencies
   - No provider code in `src/Services/`
   - Batch operations used (not single-item loops for ingestion)
   - `JSON_THROW_ON_ERROR` on all `json_decode()`
   - Custom exceptions (no `\RuntimeException`)
   - No `@phpstan-ignore`
   - Structured logging with `trace_id`
3. Run: !`composer analyse 2>&1`
4. List issues by severity: **Critical** / **Major** / **Minor**
