<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Services\Chunking;

use Thaolaptrinh\Rag\Contracts\Chunker;
use Thaolaptrinh\Rag\Data\Chunk;
use Thaolaptrinh\Rag\Data\Document;
use Thaolaptrinh\Rag\Exceptions\ChunkingFailedException;

final class FixedSizeChunker implements Chunker
{
    /** @var int<1, max> */
    private readonly int $chunkSize;

    /** @var int<0, max> */
    private readonly int $overlap;

    /**
     * @param  int<1, max>  $chunkSize
     * @param  int<0, max>  $overlap
     */
    public function __construct(int $chunkSize = 1000, int $overlap = 200)
    {
        if ($overlap >= $chunkSize) {
            throw ChunkingFailedException::create('Overlap must be less than chunk size');
        }

        $this->chunkSize = $chunkSize;
        $this->overlap = $overlap;
    }

    public function split(Document $document): array
    {
        $content = $document->content;

        if ($content === '') {
            return [];
        }

        $length = mb_strlen($content);

        if ($length <= $this->chunkSize) {
            return [
                Chunk::create(
                    documentId: $document->id,
                    content: $content,
                    index: 0,
                    metadata: $this->buildMetadata($document->metadata, 0, 0, $length),
                ),
            ];
        }

        $chunks = [];
        $step = $this->chunkSize - $this->overlap;
        $position = 0;
        $index = 0;

        while ($position < $length) {
            $chunkLength = min($this->chunkSize, $length - $position);
            $chunkContent = mb_substr($content, $position, $chunkLength);
            $end = $position + $chunkLength;

            $chunks[] = Chunk::create(
                documentId: $document->id,
                content: $chunkContent,
                index: $index,
                metadata: $this->buildMetadata($document->metadata, $index, $position, $end),
            );

            if ($position + $this->chunkSize >= $length) {
                break;
            }

            $position += $step;
            $index++;
        }

        return $chunks;
    }

    /**
     * @return int<1, max>
     */
    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    /**
     * @return int<0, max>
     */
    public function getOverlap(): int
    {
        return $this->overlap;
    }

    /**
     * @param  array<string, mixed>  $documentMetadata
     * @return array<string, mixed>
     */
    private function buildMetadata(array $documentMetadata, int $index, int $start, int $end): array
    {
        return array_merge($documentMetadata, [
            'chunk_index' => $index,
            'chunk_start' => $start,
            'chunk_end' => $end,
        ]);
    }
}
