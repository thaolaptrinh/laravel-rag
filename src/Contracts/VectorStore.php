<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Contracts;

use Thaolaptrinh\Rag\Data\Chunk;
use Thaolaptrinh\Rag\Data\QueryResult;

interface VectorStore
{
    /**
     * Store multiple chunks with their embeddings (batch).
     * Implementations MUST use bulk INSERT for performance.
     *
     * @param  list<array{chunk: Chunk, embedding: list<float>}>  $items
     */
    public function storeMany(array $items): void;

    /**
     * Search for similar chunks by embedding vector.
     *
     * MVP supports equality filters only: ['source' => 'pdf']
     * Future: operator support via ['page' => ['$gt' => 10]]
     *
     * @param  list<float>  $queryEmbedding
     * @param  int<1, max>  $topK
     * @param  array<string, mixed>  $filters  Metadata key-value equality filters
     * @param  float  $minScore  Minimum similarity score threshold (0.0-1.0)
     * @return list<QueryResult>
     */
    public function search(array $queryEmbedding, int $topK, array $filters = [], float $minScore = 0.0): array;

    /**
     * Get existing documents by IDs for content hash comparison.
     * Used by ingestion pipeline to skip unchanged documents.
     *
     * @param  list<string>  $ids
     * @return list<array{id: string, content_hash: string}>
     */
    public function getDocumentsByIds(array $ids): array;

    /**
     * Delete all chunks for a document AND the document record itself.
     *
     * @return int Number of deleted chunks (document record is deleted implicitly via FK cascade)
     */
    public function deleteByDocumentId(string $documentId): int;

    /**
     * Delete all chunks for multiple documents AND their document records.
     *
     * @param  list<string>  $documentIds
     * @return int Number of deleted chunks
     */
    public function deleteByDocumentIds(array $documentIds): int;

    /**
     * Delete all documents and chunks.
     */
    public function truncate(): void;
}
