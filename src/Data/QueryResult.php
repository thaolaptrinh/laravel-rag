<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Data;

final readonly class QueryResult
{
    private function __construct(
        public string $content,
        public float $score,
        /** @var array<string, mixed> */
        public array $metadata,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function create(string $content, float $score, array $metadata = []): self
    {
        return new self(content: $content, score: $score, metadata: $metadata);
    }
}
