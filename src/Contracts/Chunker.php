<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Contracts;

interface Chunker
{
    /**
     * Split content into chunks with metadata preservation
     *
     * @param array{id: string, content: string, metadata: array<string, mixed>} $document
     * @return array<int, array{id: string, content: string, metadata: array<string, mixed>, index: int}>
     */
    public function chunk(array $document): array;

    /**
     * Get the maximum chunk size
     *
     * @return int<1, max>
     */
    public function getMaxChunkSize(): int;

    /**
     * Get the chunk overlap size
     *
     * @return int<0, max>
     */
    public function getOverlap(): int;
}
