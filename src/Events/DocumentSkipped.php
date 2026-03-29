<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Events;

use Carbon\CarbonImmutable;

final readonly class DocumentSkipped
{
    public function __construct(
        public string $documentId,
        public string $reason,
        public string $traceId,
        public CarbonImmutable $createdAt,
    ) {}
}
