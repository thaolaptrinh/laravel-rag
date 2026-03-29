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
        $indexes = [
            'idx_chunks_embedding_hnsw' => 'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_chunks_embedding_hnsw ON rag_chunks USING hnsw (embedding vector_cosine_ops) WITH (m = 16, ef_construction = 64)',
            'idx_chunks_metadata_gin' => 'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_chunks_metadata_gin ON rag_chunks USING gin (metadata jsonb_path_ops)',
            'idx_chunks_content_tsv_gin' => 'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_chunks_content_tsv_gin ON rag_chunks USING gin (content_tsv)',
        ];

        foreach ($indexes as $name => $sql) {
            try {
                DB::connection('rag')->statement($sql);
            } catch (Throwable $e) {
                if ($name === 'idx_chunks_embedding_hnsw') {
                    DB::connection('rag')->statement("DO \$\$ BEGIN RAISE NOTICE 'HNSW index skipped - run php artisan rag:index after ingesting documents'; END \$\$");
                }
            }
        }
    }

    public function down(): void
    {
        $indexes = [
            'idx_chunks_content_tsv_gin',
            'idx_chunks_metadata_gin',
            'idx_chunks_embedding_hnsw',
        ];

        foreach ($indexes as $name) {
            try {
                DB::connection('rag')->statement("DROP INDEX CONCURRENTLY IF EXISTS {$name}");
            } catch (Throwable) {
            }
        }
    }
};
