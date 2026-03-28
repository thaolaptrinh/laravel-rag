---
description: Format all code with Laravel Pint
---

Format code: !`composer format 2>&1`

Then check: !`git diff --stat 2>&1` to see which files changed.

If Pint reports errors, fix them manually — Pint should auto-fix style issues.
