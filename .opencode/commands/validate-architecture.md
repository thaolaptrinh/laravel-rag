---
description: Validate architecture and code quality
agent: laravel-expert
---

Validate architecture and code quality for production readiness:

## 1. Provider Abstraction Checks

!`grep -r "OpenAI\|Pinecone\|Anthropic" src/Services/ || echo "✅ No provider code in Services"`

!`grep -r "new OpenAI\|new Pinecone\|new Anthropic" src/Services/ || echo "✅ No hard-coded providers"`

## 2. Code Quality Checks

### Strict Types Check
!`find src -name "*.php" -exec grep -L "declare(strict_types=1)" {} \; | wc -l`

# Count should match total PHP files
!`find src -name "*.php" | wc -l`

!`echo "Files with strict_types: $(find src -name "*.php" -exec grep -L "declare(strict_types=1)" {} \; | wc -l)"`

!`echo "Total PHP files: $(find src -name "*.php" | wc -l)"`

### Typed Properties Check
!`grep -r "private readonly" src/ || echo "⚠️  Consider using typed readonly properties"`

!`grep -r "protected readonly" src/ || echo "⚠️  Consider using typed readonly properties"`

## 3. Batch Operations Check

!`grep -r "embedBatch\|storeMany" src/ || echo "⚠️  Batch operations not implemented"`

!`grep -r "function embedBatch" src/Contracts/EmbeddingDriver.php || echo "❌ embedBatch not in interface"`

!`grep -r "function storeMany" src/Contracts/VectorStore.php || echo "❌ storeMany not in interface"`

## 4. Trace ID Support

!`grep -r "trace_id" src/ || echo "⚠️  Trace ID logging not implemented"`

## 5. JSON_THROW_ON_ERROR

!`grep -r "JSON_THROW_ON_ERROR" src/ || echo "⚠️  JSON_THROW_ON_ERROR not consistently used"`

## 6. DB-Specific Logic

!`grep -r "<=>\|pgvector" src/Services/ || echo "⚠️  DB-specific logic found (should be in Retriever)"`

## 7. Return Type Declarations

Check for missing return types:
!`grep -r "function \w+\(" src/Contracts/ | grep -v ": string\|: array\|: int\|: float\|: bool" || echo "⚠️  Some methods missing return types"`

## Report

Review all check outputs and provide:
- List of any files missing strict_types
- List of methods missing return types
- Recommendations for fixing any issues found

If all checks pass, report:
```
✅ All architecture checks passed
✅ All code quality checks passed
✅ Ready for production
```
