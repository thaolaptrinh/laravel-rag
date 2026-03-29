<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Events;

use Carbon\CarbonImmutable;
use Throwable;

final readonly class DocumentIngestionFailed
{
    public function __construct(
        public string $documentId,
        public string $reason,
        public string $traceId,
        public ?Throwable $throwable,
        public CarbonImmutable $createdAt,
    ) {}
}
