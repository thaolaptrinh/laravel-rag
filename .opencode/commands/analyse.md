---
description: Run PHPStan static analysis and fix errors
---

Run PHPStan: !`composer analyse 2>&1`

If there are errors, analyze each one and fix immediately following these rules:
- NEVER add `@phpstan-ignore` — fix the actual type problem
- Use `is_array()`, `is_string()` guards for untyped data
- Use specific PHPDoc array shapes (`list<Chunk>`, `array<string, mixed>`)
- Add explicit return types to all methods
- Add `declare(strict_types=1)` if missing

Re-run to confirm 0 errors. Never commit with PHPStan errors.
