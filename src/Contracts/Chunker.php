<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Contracts;

use Thaolaptrinh\Rag\Data\Chunk;
use Thaolaptrinh\Rag\Data\Document;

interface Chunker
{
    /**
     * Split a document into chunks.
     * Each chunk inherits the document's metadata.
     * Implementations may add chunk-specific metadata (e.g., 'chunk_index', 'offset').
     *
     * @param  Document  $document  The document to split
     * @return list<Chunk>
     */
    public function split(Document $document): array;

    /**
     * Get configured chunk size in characters.
     *
     * @return int<1, max>
     */
    public function getChunkSize(): int;

    /**
     * Get configured overlap in characters.
     *
     * @return int<0, max>
     */
    public function getOverlap(): int;
}
