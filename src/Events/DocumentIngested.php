<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Events;

use Carbon\CarbonImmutable;

final readonly class DocumentIngested
{
    public function __construct(
        public string $documentId,
        public int $chunkCount,
        public string $traceId,
        public CarbonImmutable $createdAt,
    ) {}
}
