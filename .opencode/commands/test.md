---
description: Run Pest test suite and analyze failures
agent: laravel-expert
template: Run Pest test suite and analyze failures
---

Run the Pest test suite:

!`composer test 2>&1`

If there are failures:
1. Read each failure message carefully
2. Open the relevant test file and implementation
3. Identify the root cause (do not just fix the symptom)
4. Fix the implementation or the test depending on which is wrong
5. Re-run that specific test to confirm

If all tests pass: report the number of tests passed and duration.
