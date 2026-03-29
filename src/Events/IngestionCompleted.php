<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Events;

use Carbon\CarbonImmutable;

final readonly class IngestionCompleted
{
    public function __construct(
        public int $ingested,
        public int $skipped,
        public int $errors,
        public int $durationMs,
        public string $traceId,
        public CarbonImmutable $createdAt,
    ) {}
}
