<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    protected $connection = 'rag';

    public function up(): void
    {
        DB::connection('rag')->statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_chunks_embedding_hnsw ON rag_chunks USING hnsw (embedding vector_cosine_ops) WITH (m = 16, ef_construction = 64)');
        DB::connection('rag')->statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_chunks_metadata_gin ON rag_chunks USING gin (metadata jsonb_path_ops)');
        DB::connection('rag')->statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_chunks_content_tsv_gin ON rag_chunks USING gin (content_tsv)');
    }

    public function down(): void
    {
        DB::connection('rag')->statement('DROP INDEX CONCURRENTLY IF EXISTS idx_chunks_content_tsv_gin');
        DB::connection('rag')->statement('DROP INDEX CONCURRENTLY IF EXISTS idx_chunks_metadata_gin');
        DB::connection('rag')->statement('DROP INDEX CONCURRENTLY IF EXISTS idx_chunks_embedding_hnsw');
    }
};
