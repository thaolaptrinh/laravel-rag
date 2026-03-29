<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Services;

use Illuminate\Support\Facades\Log;

class RagLogger
{
    public function __construct(
        private readonly string $channel,
    ) {}

    public function ingestionStart(string $traceId, int $documentCount): void
    {
        $this->log('info', 'Ingestion pipeline started', [
            'trace_id' => $traceId,
            'pipeline' => 'ingestion',
            'step' => 'start',
            'documents_count' => $documentCount,
        ]);
    }

    public function ingestionSkipped(string $traceId, string $documentId, string $reason): void
    {
        $this->log('warning', 'Document skipped', [
            'trace_id' => $traceId,
            'pipeline' => 'ingestion',
            'step' => 'hash_check',
            'document_id' => $documentId,
            'reason' => $reason,
        ]);
    }

    public function ingestionDocumentComplete(string $traceId, string $documentId, int $chunkCount): void
    {
        $this->log('info', 'Document ingested', [
            'trace_id' => $traceId,
            'pipeline' => 'ingestion',
            'step' => 'store',
            'document_id' => $documentId,
            'chunks_count' => $chunkCount,
        ]);
    }

    public function ingestionDocumentFailed(string $traceId, string $documentId, string $reason): void
    {
        $this->log('error', 'Document ingestion failed', [
            'trace_id' => $traceId,
            'pipeline' => 'ingestion',
            'step' => 'ingestion',
            'document_id' => $documentId,
            'reason' => $reason,
        ]);
    }

    public function ingestionComplete(string $traceId, int $ingested, int $skipped, int $errors, int $durationMs): void
    {
        $this->log('info', 'Ingestion pipeline completed', [
            'trace_id' => $traceId,
            'pipeline' => 'ingestion',
            'step' => 'complete',
            'ingested' => $ingested,
            'skipped' => $skipped,
            'errors' => $errors,
            'duration_ms' => $durationMs,
        ]);
    }

    public function queryStart(string $traceId, string $question): void
    {
        $this->log('info', 'Query pipeline started', [
            'trace_id' => $traceId,
            'pipeline' => 'query',
            'step' => 'start',
            'question' => $question,
        ]);
    }

    public function queryComplete(string $traceId, int $chunksRetrieved, int $durationMs): void
    {
        $this->log('info', 'Query pipeline completed', [
            'trace_id' => $traceId,
            'pipeline' => 'query',
            'step' => 'complete',
            'chunks_retrieved' => $chunksRetrieved,
            'duration_ms' => $durationMs,
        ]);
    }

    public function error(string $traceId, string $pipeline, string $step, string $message, ?\Throwable $previous = null): void
    {
        $this->log('error', $message, [
            'trace_id' => $traceId,
            'pipeline' => $pipeline,
            'step' => $step,
        ], $previous);
    }

    public function warning(string $traceId, string $pipeline, string $step, string $message): void
    {
        $this->log('warning', $message, [
            'trace_id' => $traceId,
            'pipeline' => $pipeline,
            'step' => $step,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(string $level, string $message, array $context, ?\Throwable $previous = null): void
    {
        $logger = Log::channel($this->channel);

        if ($previous instanceof \Throwable) {
            $context['exception'] = $previous;
        }

        match ($level) {
            'info' => $logger->info($message, $context),
            'warning' => $logger->warning($message, $context),
            'error' => $logger->error($message, $context),
            default => $logger->info($message, $context),
        };
    }
}
