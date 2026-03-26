---
description: Set up Phase 1 - Core abstractions and ingestion foundation
agent: laravel-expert
---

This command guides you through setting up Phase 1 of the RAG pipeline.

## What We'll Build

### 1. Define Core Interfaces
Create all interface files in `src/Contracts/`:
- DataSource.php
- Chunker.php
- EmbeddingDriver.php
- VectorStore.php
- Retriever.php
- PromptBuilder.php
- LlmDriver.php

### 2. Implement Ingestion Components
- TextDataSource (drivers/data-source/)
- TextChunker (services/)

### 3. Set Up Database Schema
- Create migration for rag_chunks table
- Add pgvector extension support

### 4. Validate Each Component
- Test DataSource loads text
- Test Chunker creates reasonable chunks
- Verify schema is correct

## Step-by-Step

### Step 1: Create Interfaces
Use the laravel-expert agent to create all interface files with proper documentation.

### Step 2: Implement DataSource
Create TextDataSource that:
- Checks if file exists
- Reads file content
- Returns array with id, content, and metadata

### Step 3: Implement Chunker
Create TextChunker that:
- Splits text by character limit (configurable)
- Preserves paragraph boundaries
- Returns array with content, metadata, and index

### Step 4: Create Database Schema
Create migration for:
- rag_chunks table (id, document_id, chunk_index, embedding, content, metadata, deleted_at, timestamps)
- pgvector extension
- Unique constraint on (document_id, chunk_index)
- Index on deleted_at

### Step 5: Validate Each Component
Run tests to verify each component works independently.

## Validation Checklist
- [ ] All interfaces created with proper methods
- [ ] DataSource returns proper structure (with id)
- [ ] Chunker preserves metadata and adds index
- [ ] Migration created and tested
- [ ] PHPStan passes (no errors)

## Next Steps
After Phase 1 is complete, run `/phase-2-ingestion` to continue.
