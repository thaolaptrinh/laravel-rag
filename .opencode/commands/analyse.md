---
description: Run PHPStan static analysis
agent: build
---

Run PHPStan static analysis at level 6:

!`vendor/bin/phpstan analyse --level=6`

If errors are found:
1. Analyze each error
2. Fix type hints
3. Add missing return types
4. Fix any other issues
5. Re-run until clean

Common issues to fix:
- Missing return types on methods
- Missing parameter types
- Missing strict_types declarations
- Unused variables
- Invalid PHPDoc
- Mixed type usage
