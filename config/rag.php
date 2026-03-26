<?php

declare(strict_types=1);

return [
    'data_source' => [
        'type' => env('RAG_DATA_SOURCE', 'text'),
    ],

    'chunker' => [
        'max_chunk_size' => (int) env('RAG_MAX_CHUNK_SIZE', 1000),
        'overlap' => (int) env('RAG_CHUNK_OVERLAP', 200),
    ],

    'embedding' => [
        'provider' => env('RAG_EMBEDDING_PROVIDER', 'openai'),
        'api_key' => env('RAG_OPENAI_API_KEY') ?: env('OPENAI_API_KEY'),
        'model' => env('RAG_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'dimension' => (int) env('RAG_EMBEDDING_DIMENSION', 1536),
        'api_url' => env('RAG_EMBEDDING_API_URL'),
    ],

    'vector_store' => [
        'provider' => env('RAG_VECTOR_STORE', 'pgvector'),
        'table' => env('RAG_VECTOR_TABLE', 'rag_chunks'),
    ],

    'retriever' => [
        'type' => env('RAG_RETRIEVER_TYPE', 'similarity'),
        'table' => env('RAG_VECTOR_TABLE', 'rag_chunks'),
    ],

    'prompt' => [
        'system' => env(
            'RAG_PROMPT_SYSTEM',
            'You are a helpful assistant. Answer the question based on the provided context.'
        ),
        'average_tokens_per_word' => (int) env('RAG_AVG_TOKENS_PER_WORD', 4),
    ],

    'llm' => [
        'provider' => env('RAG_LLM_PROVIDER', 'openai'),
        'api_key' => env('RAG_OPENAI_API_KEY') ?: env('OPENAI_API_KEY'),
        'model' => env('RAG_LLM_MODEL', 'gpt-4o-mini'),
        'max_tokens' => (int) env('RAG_LLM_MAX_TOKENS', 4096),
        'temperature' => (float) env('RAG_LLM_TEMPERATURE', 0.7),
        'api_url' => env('RAG_LLM_API_URL'),
    ],
];
