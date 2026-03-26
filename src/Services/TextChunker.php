<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Services;

use Illuminate\Support\Str;
use Thaolaptrinh\Rag\Contracts\Chunker;

class TextChunker implements Chunker
{
    private readonly int $maxChunkSize;

    private readonly int $overlap;

    public function __construct(int $maxChunkSize = 1000, int $overlap = 200)
    {
        $this->maxChunkSize = $maxChunkSize;
        $this->overlap = $overlap;

        if ($this->overlap >= $this->maxChunkSize) {
            throw new \InvalidArgumentException('Overlap must be less than max chunk size');
        }
    }

    /**
     * Split content into chunks with metadata preservation
     *
     * @param  array{id: string, content: string, metadata: array<string, mixed>}  $document
     * @return array<int, array{id: string, content: string, metadata: array<string, mixed>, index: int}>
     */
    public function chunk(array $document): array
    {
        $content = $document['content'];
        $chunks = [];
        $index = 0;
        $position = 0;
        $contentLength = strlen($content);

        while ($position < $contentLength) {
            $endPosition = min($position + $this->maxChunkSize, $contentLength);
            $chunkContent = substr($content, $position, $this->maxChunkSize);

            $chunks[] = [
                'id' => Str::uuid()->toString(),
                'content' => $chunkContent,
                'metadata' => array_merge($document['metadata'], [
                    'parent_id' => $document['id'],
                    'chunk_index' => $index,
                    'chunk_start' => $position,
                    'chunk_end' => $endPosition,
                ]),
                'index' => $index,
            ];

            $position += $this->maxChunkSize - $this->overlap;
            $index++;
        }

        return $chunks;
    }

    public function getMaxChunkSize(): int
    {
        return $this->maxChunkSize;
    }

    public function getOverlap(): int
    {
        return $this->overlap;
    }
}
