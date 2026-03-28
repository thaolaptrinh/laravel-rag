<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Data;

final readonly class Chunk
{
    private function __construct(
        public string $id,
        public string $documentId,
        public string $content,
        public int $index,
        /** @var array<string, mixed> */
        public array $metadata,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function create(
        string $documentId,
        string $content,
        int $index,
        array $metadata = [],
    ): self {
        return new self(
            id: $documentId.'::chunk::'.$index,
            documentId: $documentId,
            content: $content,
            index: $index,
            metadata: $metadata,
        );
    }
}
