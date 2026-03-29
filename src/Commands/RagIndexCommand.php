<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class RagIndexCommand extends Command
{
    protected $signature = 'rag:index';

    protected $description = 'Create HNSW vector index on rag_chunks table';

    public function handle(): int
    {
        /** @var string $connection */
        $connection = config('rag.database.connection', 'rag');
        /** @var string $chunksTable */
        $chunksTable = config('rag.database.chunks_table', 'rag_chunks');

        $count = DB::connection($connection)->table($chunksTable)->count();

        if ($count === 0) {
            $this->warn('No chunks found. Ingest documents first before creating the HNSW index.');

            return Command::FAILURE;
        }

        $this->info("Creating HNSW index on {$chunksTable} ({$count} chunks)...");

        try {
            DB::connection($connection)->statement(
                'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_chunks_embedding_hnsw ON rag_chunks USING hnsw (embedding vector_cosine_ops) WITH (m = 16, ef_construction = 64)',
            );

            $this->info('HNSW index created successfully.');
        } catch (\Throwable $e) {
            $this->error('Failed to create HNSW index: '.$e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
