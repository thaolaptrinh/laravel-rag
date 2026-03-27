<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag;

use Thaolaptrinh\Rag\Services\IngestionPipeline;
use Thaolaptrinh\Rag\Services\QueryPipeline;

class RagManager
{
    public function __construct(
        private readonly IngestionPipeline $ingestionPipeline,
        private readonly QueryPipeline $queryPipeline,
    ) {}

    /**
     * Ingest data from a source
     *
     * @param  string  $source  Data source identifier
     * @return array{stored: int, errors: int, source: string}
     */
    public function ingest(string $source): array
    {
        return $this->ingestionPipeline->ingest($source);
    }

    /**
     * Query the RAG system
     *
     * @param  string  $query  User query
     * @param  int<1, max>  $topK  Number of chunks to retrieve
     * @param  array<string, mixed>  $filters  Optional filters for retrieval
     * @return array{answer: string, chunks: int, query: string}
     */
    public function query(string $query, int $topK = 5, array $filters = []): array
    {
        return $this->queryPipeline->query($query, $topK, $filters);
    }
}
