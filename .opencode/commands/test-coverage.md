---
description: Run tests with coverage report
---

Run coverage: !`composer test-coverage 2>&1`

Analyze the report:
1. Identify untested code paths
2. Focus on critical areas: Contracts, Services, Drivers
3. Suggest additional tests for:
   - Edge cases (empty input, boundary values)
   - Error paths (HTTP failures, DB errors)
   - Integration points (pipeline orchestration)
