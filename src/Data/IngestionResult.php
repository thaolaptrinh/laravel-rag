<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Data;

final readonly class IngestionResult
{
    private function __construct(
        public int $ingested,
        public int $skipped,
        public int $errors,
        public string $traceId,
    ) {}

    public static function create(int $ingested, int $skipped, int $errors, string $traceId): self
    {
        return new self(ingested: $ingested, skipped: $skipped, errors: $errors, traceId: $traceId);
    }
}
