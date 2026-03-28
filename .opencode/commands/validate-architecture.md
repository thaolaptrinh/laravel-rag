---
description: Validate architecture constraints and code quality
subtask: true
---

Validate the full architecture:

1. No provider lock-in in core: !`grep -r 'OpenAI\|Pinecone\|Anthropic\|Supabase' src/Services/ 2>&1 || echo "OK: No provider lock-in"`
2. All PHP files have strict types: !`find src -name '*.php' | xargs grep -L 'declare(strict_types=1)' 2>&1 || echo "OK: All files have strict_types"`
3. PHPStan clean: !`composer analyse 2>&1`
4. Tests pass: !`composer test 2>&1`
5. No @phpstan-ignore: !`grep -r '@phpstan-ignore' src/ 2>&1 || echo "OK: No phpstan-ignore"`
6. Custom exceptions only: !`grep -rn 'new \\RuntimeException\|new \\InvalidArgumentException' src/ 2>&1 || echo "OK: Only custom exceptions"`

Report: provider lock-in, missing strict_types, PHPStan errors, test failures, phpstan-ignore usage, exception violations.
