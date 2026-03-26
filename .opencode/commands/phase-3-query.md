---
description: Build Phase 3 - Query pipeline with LLM generation
agent: laravel-expert
---

This command guides you through building the complete query pipeline.

## What We'll Build

### 1. Implement Retriever
- Create SimilarityRetriever
- Accepts query string (not embeddings)
- Internally generates embedding
- Wraps VectorStore for clean abstraction

### 2. Create PromptBuilder
- SimplePromptBuilder for MVP
- Combines query with retrieved context
- Enforces token limits via maxTokens parameter
- Ensure deterministic output

### 3. Implement LlmDriver
- Create OpenAILlmDriver
- Calls OpenAI API with prompt
- Returns generated response
- Handles API errors

### 4. Create QueryPipeline Service
- Orchestrate: Retriever → PromptBuilder → LlmDriver
- Note: Retriever now handles embedding internally
- Simplified orchestration

### 5. Create Artisan Command
- `php artisan rag:query <query>`
- Show retrieved chunks (with scores)
- Display generated response
- Log with trace IDs

## Step-by-Step

### Step 1: Implement SimilarityRetriever
```php
<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Services\Retrievers;

class SimilarityRetriever implements Retriever
{
    public function __construct(
        private readonly EmbeddingDriver $embedder,
        private readonly string $connection = 'default',
        private readonly string $table = 'rag_chunks'
    ) {}
    
    /**
     * @return array<int, array{content: string, score: float, metadata: array}>
     */
    public function retrieve(string $query, int $topK, array $filters = []): array
    {
        // Generate embedding internally
        $queryEmbedding = $this->embedder->embed($query);
        
        // Query database with pgvector
        // Add alias 'score' for similarity
        // Note: This is DB-specific but acceptable for MVP
        // TODO: Isolate in driver or store later
        // ... implementation ...
    }
}
```

### Step 2: Create SimplePromptBuilder
```php
<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Services;

class SimplePromptBuilder implements PromptBuilder
{
    /**
     * @return string
     */
    public function build(string $query, array $chunks, int $maxTokens = 4000): string
    {
        // Limit context to fit in LLM window
        // Ensure deterministic formatting
        // ... implementation ...
    }
}
```

### Step 3: Implement OpenAILlmDriver
```php
<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Drivers\Llm;

use Thaolaptrinh\Rag\Contracts\LlmDriver;
use Thaolaptrinh\Rag\Exceptions\GenerationFailedException;

class OpenAILlmDriver implements LlmDriver
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gpt-4o-mini',
        private readonly int $timeout = 60
    ) {}
    
    /**
     * @return string
     */
    public function generate(string $prompt): string
    {
        // Call OpenAI API
        // Handle errors
        // ... implementation ...
    }
}
```

### Step 4: Create QueryPipeline
```php
<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Services;

use Thaolaptrinh\Rag\Contracts\Retriever;
use Thaolaptrinh\Rag\Contracts\PromptBuilder;
use Thaolaptrinh\Rag\Contracts\LlmDriver;

class QueryPipeline
{
    public function __construct(
        private readonly Retriever $retriever,
        private readonly PromptBuilder $promptBuilder,
        private readonly LlmDriver $llm
    ) {}
    
    /**
     * @return string
     */
    public function query(string $query, int $topK = 5, int $maxTokens = 4000): string
    {
        // Retriever handles embedding internally
        $chunks = $this->retriever->retrieve($query, $topK);
        
        // Build prompt with token limits
        $prompt = $this->promptBuilder->build($query, $chunks, $maxTokens);
        
        // Generate response
        return $this->llm->generate($prompt);
    }
}
```

### Step 5: Create Artisan Command
Create `rag:query` command that:
- Takes query as argument
- Shows retrieved chunks (with scores)
- Displays generated response
- Logs with trace IDs

### Step 6: Test End-to-End
```bash
# First ingest some data
php artisan rag:ingest ./tests/fixtures/document.txt

# Then query
php artisan rag:query "What is the main topic?"

# Verify response is relevant
# Check logs for trace IDs
tail -f storage/logs/laravel.log | grep trace_id
```

## Validation Checklist
- [ ] SimilarityRetriever retrieves relevant chunks
- [ ] PromptBuilder constructs valid prompts
- [ ] PromptBuilder enforces token limits
- [ ] LlmDriver generates responses
- [ ] QueryPipeline orchestrates correctly
- [ ] Artisan command works
- [ ] Retrieved chunks are relevant
- [ ] Generated responses make sense
- [ ] All files use declare(strict_types=1)
- [ ] All methods have explicit types
- [ ] Logs contain trace IDs
- [ ] PHPStan passes
- [ ] Tests pass
- [ ] Batch operations used where appropriate

## Next Steps
After Phase 3 is complete, run `/phase-4-integration` for Laravel integration.
