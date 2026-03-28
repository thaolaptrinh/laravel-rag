---
description: Run Pest test suite
---

Run tests: !`composer test 2>&1`

If there are failures, analyze each one:
1. Read the error message and stack trace
2. Read the relevant test and source files
3. Identify root cause (test expectation wrong vs implementation bug)
4. Fix one test at a time
5. Re-run to confirm the fix before moving to the next

If a test is an architecture test (`tests/ArchTest.php`), check AGENTS.md for the rule it enforces.
