---
description: Build Phase 2 - Complete ingestion pipeline
agent: laravel-expert
---

This command guides you through completing the ingestion pipeline.

## What We'll Build

### 1. Complete VectorStore
- Finish PgVectorStore implementation
- Implement store() and storeMany() methods
- Implement delete() method with soft delete support

### 2. Create IngestionPipeline Service
- Orchestrate: DataSource → Chunker → EmbeddingDriver → VectorStore
- Use batch operations (embedBatch, storeMany)
- Handle errors gracefully with structured logging
- Return detailed stats

### 3. Create Artisan Command
- `php artisan rag:ingest <source>`
- Validate input
- Show progress
- Report results with stats

### 4. End-to-End Validation
- Test with real file
- Verify data persists in database
- Check logs for trace IDs
- Query back to validate storage

## Step-by-Step

### Step 1: Implement PgVectorStore
Complete the implementation:
- store() - Single vector storage
- storeMany() - Batch vector storage (preferred for ingestion)
- delete() - Soft delete with deleted_at timestamp

Use insertOrIgnore() or handle unique constraint exceptions for idempotency.

### Step 2: Create IngestionPipeline
```php
<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Services;

class IngestionPipeline
{
    public function __construct(
        private readonly DataSource $dataSource,
        private readonly Chunker $chunker,
        private readonly EmbeddingDriver $embedder,
        private readonly VectorStore $store
    ) {}
    
    /**
     * @return array{stored: int, errors: int, source: string}
     */
    public function ingest(string $source): array
    {
        // Load documents, chunk, batch embed, batch store
        // Use RagLogger for structured logging
        // Return stats
    }
}
```

### Step 3: Create Artisan Command
Create `rag:ingest` command that:
- Takes source file path as argument
- Uses IngestionPipeline
- Shows progress (dots or progress bar)
- Reports success/failure with detailed stats

### Step 4: Test End-to-End
```bash
# Create test file
echo "This is a test document about RAG systems." > /tmp/test.txt

# Run ingestion
php artisan rag:ingest /tmp/test.txt

# Verify in database
php artisan tinker --execute="
\$count = DB::table('rag_chunks')->count();
echo 'Stored ' . \$count . ' chunks' . PHP_EOL;
"

# Check logs for trace IDs
tail -f storage/logs/laravel.log | grep trace_id
```

## Validation Checklist
- [ ] PgVectorStore stores vectors correctly
- [ ] PgVectorStore supports batch operations
- [ ] IngestionPipeline orchestrates correctly
- [ ] Batch operations used (embedBatch, storeMany)
- [ ] Artisan command works
- [ ] Data persists in database
- [ ] Can query back stored data
- [ ] Logs contain trace IDs
- [ ] PHPStan passes
- [ ] Tests pass

## Next Steps
After Phase 2 is complete, run `/phase-3-query` to build the query pipeline.
