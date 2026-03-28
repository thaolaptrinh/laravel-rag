---
description: "Phase 6 — Polish: docs, CI, final validation"
subtask: true
---

Implement Phase 6: Polish

Read @docs/IMPLEMENTATION_PLAN.md (Phase 6 section) for full task details.

## Prerequisites
Phase 5 must be complete. Run `/ci` to verify everything passes.

## Tasks

### 6.1 Update AGENTS.md
- Verify rules match finalized architecture
- Remove any stale references

### 6.2 README.md
- Installation instructions
- Quick start guide
- Configuration reference (all RAG_* env vars)
- API reference

### 6.3 CI workflow
- `.github/workflows/run-tests.yml` with pgvector service
- `.github/workflows/phpstan.yml` verifying level 9

### 6.4 Final validation
Run ALL in order:
1. `composer analyse` — 0 errors
2. `composer test` — all pass
3. `vendor/bin/rector process --dry-run` — 0 changes
4. `vendor/bin/pint --test` — no issues
5. Cross-check: every interface method implemented, every config key used, every exception thrown
