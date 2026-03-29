<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Thaolaptrinh\Rag\Data\Document;
use Thaolaptrinh\Rag\Events\DocumentIngestionFailed;
use Thaolaptrinh\Rag\Services\Pipelines\IngestionPipeline;

final class RagIngestJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout;

    public function __construct(
        private readonly string $documentId,
        private readonly string $documentContent,
        /** @var array<string, mixed> */
        private readonly array $documentMetadata,
        ?string $queue = null,
    ) {
        $this->onQueue($queue);
        /** @var int<1, max> $timeout */
        $timeout = config('rag.ingestion.pipeline_timeout', 600);
        $this->timeout = $timeout;
    }

    public function handle(IngestionPipeline $pipeline): void
    {
        $document = Document::create(
            content: $this->documentContent,
            metadata: $this->documentMetadata,
            id: $this->documentId,
        );

        $pipeline->run($document);
    }

    public function failed(\Throwable $exception): void
    {
        event(new DocumentIngestionFailed(
            documentId: $this->documentId,
            reason: $exception->getMessage(),
            traceId: '',
            throwable: $exception,
            createdAt: CarbonImmutable::now(),
        ));
    }

    public function retryUntil(): \DateTimeInterface
    {
        /** @var int<1, max> $timeout */
        $timeout = config('rag.ingestion.pipeline_timeout', 600);

        return now()->addSeconds($timeout);
    }
}
