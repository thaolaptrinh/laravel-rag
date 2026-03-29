<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Services\Retrieving;

use Thaolaptrinh\Rag\Contracts\EmbeddingDriver;
use Thaolaptrinh\Rag\Contracts\Retriever;
use Thaolaptrinh\Rag\Contracts\VectorStore;
use Thaolaptrinh\Rag\Data\QueryResult;
use Thaolaptrinh\Rag\Exceptions\EmbeddingFailedException;
use Thaolaptrinh\Rag\Exceptions\RetrievalFailedException;
use Thaolaptrinh\Rag\Exceptions\StorageFailedException;

final class SimilarityRetriever implements Retriever
{
    public function __construct(
        private readonly EmbeddingDriver $embedder,
        private readonly VectorStore $store,
        private readonly float $minScore = 0.0,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return list<QueryResult>
     */
    public function retrieve(string $query, int $topK, array $filters = []): array
    {
        try {
            $queryEmbedding = $this->embedder->embed($query);

            return $this->store->search($queryEmbedding, $topK, $filters, $this->minScore);
        } catch (EmbeddingFailedException $e) {
            throw RetrievalFailedException::create('Failed to embed query', $e);
        } catch (StorageFailedException $e) {
            throw RetrievalFailedException::create('Failed to search vector store', $e);
        }
    }
}
