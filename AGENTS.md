# Laravel RAG - Agent Rules

This document defines the global system rules for the Laravel RAG package development.

## Architecture Rules

### Pipeline-Based Design
- **Ingestion Pipeline**: Separate pipeline for data ingestion (DataSource → Chunker → EmbeddingDriver → VectorStore)
- **Query Pipeline**: Separate pipeline for queries (Query → Retriever → PromptBuilder → LlmDriver)
- **No mixing**: Never combine ingestion and query logic in the same service
- **Independence**: Each pipeline should be independently testable and deployable

### Separation of Concerns
- **Core Domain**: Interfaces and services must remain provider-agnostic
- **Driver Layer**: All provider-specific code lives in Drivers namespace
- **Service Layer**: Business logic only, no external API calls
- **Configuration**: Provider selection via config only

### No Tight Coupling
- Services depend on interfaces, not concrete implementations
- Use dependency injection throughout
- No static dependencies on external services
- Facades only for Laravel integration layer

## Dependency Rules

### No Direct External APIs in Core
- **Core Services**: MUST NOT call OpenAI, Anthropic, Pinecone APIs directly
- **All Integrations**: MUST go through driver interfaces
- **Service Boundaries**: IngestionPipeline and QueryPipeline are provider-agnostic

### Driver Abstraction
- **EmbeddingDriver**: Interface for all embedding providers
- **VectorStore**: Interface for all vector databases
- **LlmDriver**: Interface for all LLM providers
- **DataSource**: Interface for all data sources
- **Retriever**: Interface for all retrieval strategies

### Configuration-Driven Provider Selection
- Providers selected via `config/rag.php`
- Service provider binds implementations based on config
- No hardcoded provider references in services
- Match expressions for clean provider switching

## Code Quality Rules

### Strict Typing (NON-NEGOTIABLE)
- **Every PHP file**: MUST start with `declare(strict_types=1);`
- **All methods**: MUST have explicit parameter types
- **All methods**: MUST have explicit return types
- **No mixed types**: Use specific array shapes in PHPDoc
- **Readonly properties**: Constructor dependencies MUST use `readonly`

### PHPStan Compatibility
- **Minimum level**: 6+
- **Zero tolerance**: All code must pass PHPStan analysis
- **Run before commit**: Always run `composer analyse` before committing
- **Fix immediately**: Never commit with PHPStan errors

### Production Best Practices
- **Batch operations**: Use `embedBatch()` and `storeMany()` (10-50x performance)
- **JSON_THROW_ON_ERROR**: Required for all JSON decoding
- **Custom exceptions**: Use domain-specific exceptions
- **Structured logging**: All logs include trace_id and pipeline_stage
- **Duplicate safety**: Handle unique constraint violations gracefully
- **Idempotent operations**: Ingestion must be safe to retry

## Development Rules

### Phase-Based Development
Follow the 4-phase implementation order:

1. **Phase 1**: Core abstractions + ingestion foundation
   - Define all interfaces
   - Implement DataSource and Chunker
   - Create database migration
   - Validate with PHPStan

2. **Phase 2**: Complete ingestion pipeline
   - Implement EmbeddingDriver with embedBatch()
   - Implement VectorStore with storeMany()
   - Implement IngestionPipeline service
   - Test end-to-end ingestion

3. **Phase 3**: Query + generation pipeline
   - Implement Retriever (accepts string query)
   - Implement PromptBuilder with token limits
   - Implement LlmDriver
   - Implement QueryPipeline service

4. **Phase 4**: Laravel integration
   - Create service provider
   - Create facade
   - Create configuration
   - Write documentation

### Validation Steps
- **Never skip**: PHPStan validation after each phase
- **Never skip**: Manual testing with sample data
- **Never skip**: Architecture validation (no provider lock-in)
- **Fix before proceeding**: Do not continue to next phase with errors

### No New Abstractions
- **Use existing interfaces**: Do not create new interfaces without justification
- **Follow patterns**: Use established patterns from existing code
- **Simple over clever**: Prefer clear, simple code
- **YAGNI principle**: Do not add features not needed for MVP

## Constraints

### Keep Core Generic
- **No file-specific logic**: DataSource is generic, not "DocumentLoader"
- **No database assumptions**: VectorStore interface works with any database
- **No model lock-in**: Easy to swap OpenAI for Anthropic, Cohere, local models
- **Extensibility**: New drivers implement interfaces, no core changes needed

### Avoid Vendor Lock-In
- **Interface boundaries**: Clear separation between core and providers
- **Swappable drivers**: Change config to switch providers
- **No provider language**: Core code never mentions "OpenAI", "Pinecone", etc.
- **Standard protocols**: Use industry-standard patterns (embeddings, vectors)

### Avoid Over-Engineering
- **MVP focus**: Text files only in Phase 1-3
- **Simple solutions**: Basic similarity search, no reranking
- **Pragmatic choices**: pgvector for MVP (not custom vector DB)
- **Future extensibility**: Architecture supports advanced features later

## Critical Success Factors

### Must Have
- ✅ Provider-agnostic architecture
- ✅ Batch operations for performance
- ✅ Idempotent ingestion
- ✅ Structured logging with trace IDs
- ✅ PHPStan level 6+ compatible
- ✅ Duplicate-safe database operations

### Must Not Have
- ❌ Provider code in core services
- ❌ Direct API calls in pipelines
- ❌ File-specific data source interface
- ❌ Single-item embed/store operations in pipelines
- ❌ Missing strict_types declarations
- ❌ Unhandled unique constraint violations

## Development Workflow

1. **Before coding**: Read relevant AGENTS.md sections
2. **While coding**: Follow code quality rules strictly
3. **After coding**: Run PHPStan and fix all errors
4. **Before commit**: Ensure all validation steps pass
5. **Before PR**: Review architecture rules for compliance

## Git Conventions

### Conventional Commits (REQUIRED)
Format: `type(scope): description`

| Type | When to use |
|------|-------------|
| `feat` | New feature |
| `fix` | Bug fix |
| `refactor` | Code change that is not a feature or bug fix |
| `test` | Adding or updating tests |
| `docs` | Documentation changes |
| `chore` | Build process, dependencies, config |
| `perf` | Performance improvement |

Scopes: `contracts`, `drivers`, `services`, `commands`, `tests`, `config`

Examples:
- `feat(drivers): add OpenAI embedding driver with batch support`
- `fix(services): handle unique constraint violation in storeMany`
- `test(ingestion): add end-to-end pipeline integration test`
- `refactor(retriever): extract embedding logic to EmbeddingDriver`

### Commit Rules
- Small, focused commits (one logical change per commit)
- Always run `composer analyse` before committing
- Always run `composer test` before pushing
- Never commit `.env`, `vendor/`, `build/`

## OpenCode Commands Reference

| Command | Purpose |
|---------|---------|
| `/analyse` | Run PHPStan and fix errors |
| `/lint` | Format code with Laravel Pint |
| `/test` | Run Pest test suite |
| `/test-coverage` | Run tests with coverage report |
| `/review` | Review changes before committing |
| `/commit` | Suggest a conventional commit message |
| `/refactor` | Run Rector auto-refactor (dry-run first) |
| `/debug <issue>` | Debug a pipeline issue systematically |
| `/phase-1-foundation` | Build Phase 1 |
| `/phase-2-ingestion` | Build Phase 2 |
| `/phase-3-query` | Build Phase 3 |
| `/validate-architecture` | Validate full architecture |

## Response Preferences

- **Code**: Always provide full implementation, never truncate
- **Errors**: Explain root cause before proposing a fix
- **PHPStan**: Never suggest `@phpstan-ignore` — fix the actual problem
- **Brevity**: Do not over-explain things that are obvious from the code

## When in Doubt

- **Question**: Should I add this feature?
  - **Answer**: Is it needed for MVP? If no, defer it.

- **Question**: Should I use this provider?
  - **Answer**: Is there an interface for it? If no, create interface first.

- **Question**: Should I skip PHPStan validation?
  - **Answer**: NEVER. Fix all errors before proceeding.

- **Question**: Should I add a shortcut?
  - **Answer**: NO. Batch operations are required for production performance.

---

**Remember**: These rules ensure the package remains maintainable, extensible, and production-ready. Follow them strictly.
