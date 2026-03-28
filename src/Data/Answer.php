<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Data;

final readonly class Answer
{
    /**
     * @param  list<QueryResult>  $sources
     */
    private function __construct(
        public string $text,
        public array $sources,
        public string $traceId,
    ) {}

    /**
     * @param  list<QueryResult>  $sources
     */
    public static function create(string $text, array $sources, string $traceId): self
    {
        return new self(text: $text, sources: $sources, traceId: $traceId);
    }
}
