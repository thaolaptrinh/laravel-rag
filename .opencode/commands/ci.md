---
description: Run full CI quality suite
---

Run the full quality suite in order:

1. Tests: !`composer test 2>&1`
2. PHPStan: !`composer analyse 2>&1`
3. Style: !`composer format 2>&1`
4. Check uncommitted changes: !`git diff --stat HEAD 2>&1`

Report a summary:
- How many tests passed / failed
- PHPStan errors (must be 0)
- Style issues
- Any uncommitted quality fixes

The build passes only when ALL checks are clean.
