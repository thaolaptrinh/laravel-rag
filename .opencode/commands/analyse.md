---
description: Run PHPStan static analysis and fix all errors
agent: laravel-expert
template: Run PHPStan and fix all errors
---

Run PHPStan static analysis at level 6:

!`composer analyse 2>&1`

If errors are found:
1. Analyze each error — understand the root cause, not just the symptom
2. Fix type hints and return types
3. Add missing `declare(strict_types=1)` where needed
4. Fix invalid PHPDoc or array shape annotations
5. Re-run until output is clean

Never use `@phpstan-ignore` — always fix the actual problem.

Common issues:
- Missing return types on interface methods
- Loosely-typed arrays (use `array{key: type}` shapes)
- Missing `declare(strict_types=1)` at file top
- Mixed type usage without narrowing
- Invalid PHPDoc annotations
