<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Services\Logging;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RagLogger
{
    private string $traceId;

    public function __construct(?string $traceId = null)
    {
        $this->traceId = $traceId ?? (string) Str::uuid();
    }

    public function getTraceId(): string
    {
        return $this->traceId;
    }

    public function ingestionStart(string $source): void
    {
        Log::info('RAG ingestion started', [
            'trace_id' => $this->traceId,
            'pipeline_stage' => 'ingestion',
            'source' => $source,
        ]);
    }

    public function ingestionComplete(string $source, int $stored): void
    {
        Log::info('RAG ingestion completed', [
            'trace_id' => $this->traceId,
            'pipeline_stage' => 'ingestion',
            'source' => $source,
            'chunks_stored' => $stored,
        ]);
    }

    public function ingestionError(string $source, string $error): void
    {
        Log::error('RAG ingestion failed', [
            'trace_id' => $this->traceId,
            'pipeline_stage' => 'ingestion',
            'source' => $source,
            'error' => $error,
        ]);
    }

    public function queryStart(string $query): void
    {
        Log::info('RAG query started', [
            'trace_id' => $this->traceId,
            'pipeline_stage' => 'query',
            'query' => $query,
        ]);
    }

    public function queryComplete(string $query, int $retrieved): void
    {
        Log::info('RAG query completed', [
            'trace_id' => $this->traceId,
            'pipeline_stage' => 'query',
            'query' => $query,
            'chunks_retrieved' => $retrieved,
        ]);
    }

    public function queryError(string $query, string $error): void
    {
        Log::error('RAG query failed', [
            'trace_id' => $this->traceId,
            'pipeline_stage' => 'query',
            'query' => $query,
            'error' => $error,
        ]);
    }

    public function embeddingBatch(int $count): void
    {
        Log::debug('RAG embedding batch', [
            'trace_id' => $this->traceId,
            'pipeline_stage' => 'ingestion',
            'batch_size' => $count,
        ]);
    }

    public function storeBatch(int $count): void
    {
        Log::debug('RAG store batch', [
            'trace_id' => $this->traceId,
            'pipeline_stage' => 'ingestion',
            'batch_size' => $count,
        ]);
    }
}
