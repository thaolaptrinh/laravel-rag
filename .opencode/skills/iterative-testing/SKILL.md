---
name: iterative-testing
description: Phase-based iterative testing workflow with production standards
license: MIT
compatibility: opencode
metadata:
  focus: production-readiness
  testing: iterative
---

## What I Do

Guide small, validated iterations: implement small part → validate → fix → proceed, with production-grade testing standards.

## Testing Philosophy

**Early Phases** (Phases 1-3):
- Focus on data flow correctness
- Simple integration tests with real providers
- CLI commands for manual validation
- Light testing, fast feedback

**Late Phase** (Phase 4):
- Comprehensive unit tests
- Mock providers for isolation
- Edge case coverage
- Error handling tests

## Workflow

### For Each Small Part:

1. **Implement Small Feature**
   - One interface or method at a time
   - Keep it focused and simple
   - Use declare(strict_types=1)

2. **Validate Immediately**
   - Run phase command
   - Check data flow
   - Verify no provider lock-in
   - Check code quality standards

3. **Fix Issues**
   - Don't proceed until working
   - Run tests until green
   - Ensure PHPStan passes

4. **Repeat**
   - Move to next small part
   - Continue until phase complete

## Phase Commands

- `/phase-1-foundation` - Set up interfaces and ingestion foundation
- `/phase-2-ingestion` - Complete ingestion pipeline
- `/phase-3-query` - Complete query pipeline with LLM
- `/validate-architecture` - Check for provider lock-in and code quality

## Code Quality Standards (NON-NEGOTIABLE)

### Mandatory Requirements
1. **Strict Typing**: Every PHP file MUST include `declare(strict_types=1);`
2. **Explicit Types**: All methods MUST have parameter and return types
3. **Typed Properties**: Constructor dependencies MUST use `readonly`
4. **Batch Operations**: Use embedBatch() and storeMany() for efficiency
5. **Structured Logging**: All logs MUST include trace_id and pipeline_stage
6. **Duplicate Safety**: Handle unique constraint exceptions gracefully

### Quality Checklist for Each Component

```php
<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Something;

class MyComponent
{
    public function __construct(
        private readonly Dependency $dependency
    ) {}
    
    /**
     * @return array{result: mixed}
     */
    public function doSomething(string $input): array
    {
        // Implementation...
    }
}
```

## Example: Implementing DataSource

### Step 1: Define Interface
```php
<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Contracts;

interface DataSource
{
    /**
     * @return array<int, array{id: string, content: string, metadata: array<string, mixed>}
     */
    public function load(string $source): array;
}
```

### Step 2: Implement
```php
<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Drivers\DataSource;

use Thaolaptrinh\Rag\Contracts\DataSource;

class TextDataSource implements DataSource
{
    /**
     * @return array<int, array{id: string, content: string, metadata: array<string, mixed>}
     */
    public function load(string $source): array
    {
        if (!file_exists($source)) {
            throw new \RuntimeException("File not found: {$source}");
        }
        
        return [
            [
                'id' => Str::uuid()->toString(),
                'content' => file_get_contents($source),
                'metadata' => ['source' => $source]
            ]
        ];
    }
}
```

### Step 3: Validate
```bash
# Create test file
echo "Sample text" > /tmp/test.txt

# Test with CLI
php artisan tinker --execute="
\$source = new \Thaolaptrinh\Rag\Drivers\DataSource\TextDataSource();
\$data = \$source->load('/tmp/test.txt');
print_r(\$data);
"

# Verify output
```

### Step 4: Add Simple Test
```php
<?php

declare(strict_types=1);

test('data source loads text file', function () {
    $source = new TextDataSource();
    $data = $source->load(__DIR__.'/fixtures/sample.txt');
    
    expect($data)->toHaveCount(1);
    expect($data[0]['content'])->toBeString();
    expect($data[0]['id'])->toBeString();
});
```

### Step 5: Run Analysis
```bash
vendor/bin/pest tests/Unit/DataSourceTest.php
vendor/bin/phpstan analyse src/Drivers/DataSource/
```

### Step 6: Fix and Repeat
- Fix any issues
- Don't proceed until green
- Then move to next component

## Phase Checklists

### Phase 1: Foundation
- [ ] All interfaces defined in Contracts namespace with explicit types
- [ ] All interfaces use declare(strict_types=1)
- [ ] TextDataSource implemented with id/content/metadata structure
- [ ] TextChunker implemented with metadata preservation and index
- [ ] PgVectorStore schema created (migration)
- [ ] Migration includes unique constraints and deleted_at
- [ ] Each component validated individually
- [ ] PHPStan passes (level 6+)

### Phase 2: Ingestion
- [ ] PgVectorStore fully implemented with batch operations (storeMany)
- [ ] OpenAIEmbeddingDriver implements embedBatch
- [ ] IngestionPipeline service created with batch operations
- [ ] RagLogger added for structured logging
- [ ] `php artisan rag:ingest` command works
- [ ] End-to-end ingestion tested
- [ ] Data persists in database
- [ ] Logs contain trace IDs
- [ ] PHPStan passes (level 6+)
- [ ] Tests pass

### Phase 3: Query + Generation
- [ ] SimilarityRetriever accepts query string (not embeddings)
- [ ] SimilarityRetriever handles embedding internally
- [ ] SimplePromptBuilder implemented with token limits
- [ ] PromptBuilder enforces deterministic output
- [ ] OpenAILlmDriver implemented
- [ ] QueryPipeline service created
- [ ] `php artisan rag:query` command works
- [ ] End-to-end query tested
- [ ] Logs contain trace IDs
- [ ] PHPStan passes (level 6+)
- [ ] Tests pass

### Phase 4: Laravel Integration
- [ ] RagServiceProvider created
- [ ] Rag facade created
- [ ] config/rag.php created with all configuration
- [ ] All services bound correctly with match statements
- [ ] Configuration controls providers
- [ ] Comprehensive tests written
- [ ] Documentation complete
- [ ] PHPStan passes (level 6+)
- [ ] Tests pass

## Validation Commands

### Check Architecture
```bash
/validate-architecture
```
Checks:
- No provider code in Services
- All Services depend on Contracts
- Configuration drives provider selection
- All files use strict_types
- Batch operations implemented
- Trace ID logging present

### Run Static Analysis
```bash
/analyse
```
Runs PHPStan at level 6

### Format Code
```bash
/lint
```
Runs Laravel Pint

## Common Issues

### Issue: Provider Code in Services
**Symptom**: grep finds "OpenAI" in Services directory
**Fix**: Move provider logic to Drivers namespace

### Issue: Missing Strict Types
**Symptom**: File missing `declare(strict_types=1);`
**Fix**: Add at top of every PHP file

### Issue: Missing Return Types
**Symptom**: PHPStan errors about missing return types
**Fix**: Add explicit return types to all methods

### Issue: Concrete Dependencies
**Symptom**: Constructor uses concrete class instead of interface
**Fix**: Change type hint to interface

### Issue: No Batch Operations
**Symptom**: Individual store/embed calls instead of batch
**Fix**: Use embedBatch() and storeMany()

### Issue: Missing Trace IDs
**Symptom**: Logs don't show trace_id
**Fix**: Use RagLogger for all logging

### Issue: Duplicate Entries
**Symptom**: Same chunk stored multiple times
**Fix**: Handle unique constraint exceptions in storeMany()

## Progress Tracking

Use the phase checklists above to track progress. Mark each item as complete before moving to the next phase.
