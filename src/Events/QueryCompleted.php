<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Events;

use Carbon\CarbonImmutable;

final readonly class QueryCompleted
{
    public function __construct(
        public string $question,
        public int $chunksRetrieved,
        public int $durationMs,
        public string $traceId,
        public CarbonImmutable $createdAt,
    ) {}
}
