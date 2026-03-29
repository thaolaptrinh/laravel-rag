<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    |
    | The database connection used by the RAG package. This must be configured
    | in your config/database.php as a separate PostgreSQL connection.
    |
    | Add the following to config/database.php connections array:
    |
    |   'rag' => [
    |       'driver' => 'pgsql',
    |       'host' => env('RAG_DB_HOST', env('DB_HOST', '127.0.0.1')),
    |       'port' => env('RAG_DB_PORT', env('DB_PORT', '5432')),
    |       'database' => env('RAG_DB_DATABASE', env('DB_DATABASE', 'forge')),
    |       'username' => env('RAG_DB_USERNAME', env('DB_USERNAME', 'forge')),
    |       'password' => env('RAG_DB_PASSWORD', env('DB_PASSWORD', '')),
    |       'prefix' => '',
    |       'prefix_indexes' => '',
    |       'search_path' => env('RAG_DB_SCHEMA', 'public'),
    |   ],
    |
    */
    'database' => [
        'connection' => env('RAG_DB_CONNECTION', 'rag'),
        'documents_table' => 'rag_documents',
        'chunks_table' => 'rag_chunks',
    ],

    /*
    |--------------------------------------------------------------------------
    | Embedding Driver
    |--------------------------------------------------------------------------
    |
    | Configuration for the HTTP-based embedding driver. Works with any
    | OpenAI-compatible embeddings API.
    |
    */
    'embedding' => [
        'api_url' => env('RAG_EMBEDDING_API_URL', 'https://api.openai.com/v1/embeddings'),
        'api_key' => env('RAG_EMBEDDING_API_KEY', ''),
        'model' => env('RAG_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'dimensions' => (int) env('RAG_EMBEDDING_DIMENSIONS', 1536),
        'batch_size' => (int) env('RAG_EMBEDDING_BATCH_SIZE', 100),
        'timeout' => (int) env('RAG_EMBEDDING_TIMEOUT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | LLM Driver
    |--------------------------------------------------------------------------
    |
    | Configuration for the HTTP-based LLM driver. Works with any
    | OpenAI-compatible chat completions API.
    |
    */
    'llm' => [
        'api_url' => env('RAG_LLM_API_URL', 'https://api.openai.com/v1/chat/completions'),
        'api_key' => env('RAG_LLM_API_KEY', ''),
        'model' => env('RAG_LLM_MODEL', 'gpt-4o-mini'),
        'max_output_tokens' => (int) env('RAG_LLM_MAX_OUTPUT_TOKENS', 4096),
        'context_window' => (int) env('RAG_LLM_CONTEXT_WINDOW', 128000),
        'temperature' => (float) env('RAG_LLM_TEMPERATURE', 0.7),
        'timeout' => (int) env('RAG_LLM_TIMEOUT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Chunking
    |--------------------------------------------------------------------------
    |
    | Configuration for the fixed-size text chunker.
    |
    */
    'chunking' => [
        'chunk_size' => (int) env('RAG_CHUNK_SIZE', 1000),
        'overlap' => (int) env('RAG_CHUNK_OVERLAP', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Document
    |--------------------------------------------------------------------------
    |
    | Document validation settings.
    |
    */
    'document' => [
        'max_content_length' => (int) env('RAG_DOCUMENT_MAX_CONTENT_LENGTH', 100_000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ingestion
    |--------------------------------------------------------------------------
    |
    | Controls how documents are processed during ingestion.
    |
    */
    'ingestion' => [
        'sub_batch_size' => (int) env('RAG_INGESTION_SUB_BATCH_SIZE', 10),
        'pipeline_timeout' => (int) env('RAG_INGESTION_PIPELINE_TIMEOUT', 600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retrieval
    |--------------------------------------------------------------------------
    |
    | Controls how relevant chunks are retrieved during queries.
    |
    */
    'retrieval' => [
        'top_k' => (int) env('RAG_RETRIEVAL_TOP_K', 20),
        'min_score' => (float) env('RAG_RETRIEVAL_MIN_SCORE', 0.0),
        'hnsw_ef_search' => (int) env('RAG_HNSW_EF_SEARCH', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Prompt
    |--------------------------------------------------------------------------
    |
    | Configuration for prompt construction and token estimation.
    |
    */
    'prompt' => [
        'system' => env('RAG_PROMPT_SYSTEM', 'You are a helpful assistant. Answer the question based only on the provided context. If the context does not contain enough information, say so.'),
        'tokens_per_char' => (float) env('RAG_PROMPT_TOKENS_PER_CHAR', 0.25),
    ],

    /*
    |--------------------------------------------------------------------------
    | Contextual Retrieval (Optional)
    |--------------------------------------------------------------------------
    |
    | When enabled, each chunk is enriched with document-level context before
    | embedding. Based on Anthropic's Contextual Retrieval research.
    |
    */
    'contextual' => [
        'enabled' => env('RAG_CONTEXTUAL_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | The log channel used for structured RAG logging with trace IDs.
    |
    */
    'logging' => [
        'channel' => env('RAG_LOG_CHANNEL', 'stack'),
    ],
];
