---
description: Debug a specific issue systematically
subtask: true
---

Debug issue: $ARGUMENTS

Follow the process:
1. **Reproduce**: Run the failing command
2. **Isolate**: Read the error and stack trace
3. **Analyze**: Read relevant source code, check against docs/ARCHITECTURE.md
4. **Hypothesize**: What is the root cause?
5. **Fix**: Apply minimal fix (no `@phpstan-ignore`, no `\RuntimeException`)
6. **Verify**: Re-run to confirm fix
7. **Report**: Explain root cause and fix

If PHPStan error: check typing, add guards, fix array shapes.
If test failure: check test expectation vs implementation.
If runtime error: check config, dependencies, HTTP responses.
