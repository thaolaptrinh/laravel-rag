<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Contracts;

use Thaolaptrinh\Rag\Data\Chunk;

interface ContextEnricher
{
    /**
     * Enrich a chunk with document-level context to improve retrieval accuracy.
     * The returned chunk has context prepended to its content.
     *
     * @param  Chunk  $chunk  The chunk to enrich
     * @param  string  $documentContent  Full document text for context
     * @param  array<string, mixed>  $documentMetadata  Document metadata
     * @return Chunk New chunk with enriched content (original chunk unchanged)
     */
    public function enrich(Chunk $chunk, string $documentContent, array $documentMetadata): Chunk;
}
