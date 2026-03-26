# Laravel RAG Implementation Plan

**Created:** 2026-03-26
**Status:** Ready to Execute

## Overview

Build a production-ready, provider-agnostic RAG (Retrieval-Augmented Generation) core package for Laravel with emphasis on modularity, strict typing, and production-grade code quality.

## Architecture Principles

1. **Provider Abstraction**: All provider interactions through interfaces
2. **Generic Design**: DataSource interface (not DocumentLoader) for any data source
3. **Retrieval Abstraction**: Retriever interface (not direct VectorStore access)
4. **Batch Operations**: Use embedBatch() and storeMany() for production performance
5. **Code Quality**: strict_types, explicit types, readonly properties, PHPStan level 6+

## Implementation Phases

### Phase 1: Core Abstractions + Ingestion Foundation

**Goal**: Define all interfaces and basic data processing components

**Tasks**:
1. Create exception classes (base RagException + 4 specific exceptions)
2. Create RagLogger for structured logging with trace IDs
3. Define all 7 core interfaces with explicit types
4. Implement TextDataSource (load text files with id/content/metadata)
5. Implement TextChunker (split text with metadata preservation)
6. Create database migration for rag_chunks table
7. Validate each component independently
8. Run PHPStan analysis

**Verification**:
- All files have `declare(strict_types=1);`
- All methods have explicit parameter and return types
- Constructor dependencies use `readonly`
- PHPStan level 6+ passes
- Manual testing with sample data

### Phase 2: Complete Ingestion Pipeline

**Goal**: Build end-to-end ingestion with batch operations

**Tasks**:
1. Implement OpenAIEmbeddingDriver with embedBatch() support
2. Implement PgVectorStore with storeMany() and duplicate handling
3. Implement IngestionPipeline service (batch ingestion)
4. Create `rag:ingest` Artisan command
5. Test end-to-end ingestion
6. Verify batch operations working
7. Verify duplicate-safe inserts
8. Verify trace ID logging

**Verification**:
- Batch operations used (not individual embed/store calls)
- Data persists in rag_chunks table
- Logs contain trace_id and pipeline_stage
- No provider lock-in in services
- PHPStan passes

### Phase 3: Query + Generation Pipeline

**Goal**: Build retrieval and LLM generation

**Tasks**:
1. Implement SimilarityRetriever (accepts query string, handles embedding internally)
2. Implement SimplePromptBuilder with token limits
3. Implement OpenAILlmDriver for response generation
4. Implement QueryPipeline service
5. Create `rag:query` Artisan command
6. Test end-to-end query flow
7. Verify retrieval accuracy
8. Verify LLM responses

**Verification**:
- Retriever accepts string query (not embeddings)
- PromptBuilder enforces maxTokens
- QueryPipeline uses contracts only
- End-to-end query produces responses
- PHPStan passes

### Phase 4: Laravel Integration

**Goal**: Complete Laravel package integration

**Tasks**:
1. Create RagServiceProvider with service bindings
2. Create Rag facade
3. Create config/rag.php with all driver configurations
4. Write comprehensive unit tests
5. Write integration tests
6. Create documentation (README, usage examples)
7. Final validation of all components

**Verification**:
- All services bound via configuration
- Providers swappable via config
- All tests pass
- Documentation complete
- PHPStan passes
- Package installable in Laravel app

## Code Quality Standards

**NON-NEGOTIABLE Requirements**:
1. Every PHP file MUST start with `declare(strict_types=1);`
2. All methods MUST have explicit parameter types
3. All methods MUST have explicit return types
4. Constructor dependencies MUST use `readonly`
5. Batch operations MUST be used (embedBatch, storeMany)
6. JSON decoding MUST use `JSON_THROW_ON_ERROR`
7. All logging MUST use RagLogger with trace IDs
8. Custom exceptions MUST be used for errors

## Directory Structure

```
src/
├── Contracts/           # All interfaces
│   ├── DataSource.php
│   ├── Chunker.php
│   ├── EmbeddingDriver.php
│   ├── VectorStore.php
│   ├── Retriever.php
│   ├── PromptBuilder.php
│   └── LlmDriver.php
├── Drivers/
│   ├── DataSource/
│   │   └── TextDataSource.php
│   ├── Embeddings/
│   │   └── OpenAIEmbeddingDriver.php
│   ├── VectorStores/
│   │   └── PgVectorStore.php
│   └── Llm/
│       └── OpenAILlmDriver.php
├── Services/
│   ├── IngestionPipeline.php
│   ├── QueryPipeline.php
│   ├── Retrievers/
│   │   └── SimilarityRetriever.php
│   └── Logging/
│       └── RagLogger.php
├── Exceptions/
│   ├── RagException.php
│   ├── EmbeddingFailedException.php
│   ├── StorageFailedException.php
│   ├── RetrievalFailedException.php
│   └── GenerationFailedException.php
├── Commands/
│   ├── IngestCommand.php
│   └── QueryCommand.php
├── Facades/
│   └── Rag.php
├── RagServiceProvider.php
└── helpers.php
```

## Pre-Build Fixes

Before starting implementation, ensure these fixes are applied:

1. **RagLogger syntax**: Fix array key syntax error
2. **RagLogger namespace**: Use `Thaolaptrinh\Rag\Logging`
3. **RagLogger imports**: Add `use Illuminate\Support\Str;`
4. **Retrieval query**: Ensure similarity score has alias
5. **JSON_THROW_ON_ERROR**: Use consistently in all JSON decoding

## Success Criteria

- [ ] All 4 phases complete
- [ ] End-to-end ingestion works
- [ ] End-to-end query works
- [ ] All tests pass
- [ ] PHPStan level 6+ passes
- [ ] No provider lock-in
- [ ] Batch operations implemented
- [ ] Structured logging with trace IDs
- [ ] Documentation complete
