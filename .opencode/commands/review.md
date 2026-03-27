---
description: Review code changes against production checklist
agent: code-reviewer
subtask: true
template: Review changes against production checklist
---

Review all current changes:

!`git diff HEAD 2>&1`

!`git status 2>&1`

Check against the AGENTS.md production checklist:

**Critical (must fix before commit):**
- `declare(strict_types=1)` on every new PHP file
- Explicit return types on every method
- No provider code in `src/Services/`
- No direct API calls in Services

**Major (should fix):**
- Batch operations (`embedBatch`, `storeMany`) used correctly
- `JSON_THROW_ON_ERROR` when decoding JSON
- Unique constraint violations handled
- Trace IDs present in logs

**Minor (can fix later):**
- Complete PHPDoc
- Test coverage for new code

Conclude with: ✅ Ready to commit or ❌ Fix required with a specific list.
