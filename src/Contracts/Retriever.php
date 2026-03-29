<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Contracts;

use Thaolaptrinh\Rag\Data\QueryResult;

interface Retriever
{
    /**
     * Retrieve relevant chunks for a query.
     * Handles embedding internally — callers pass raw text, not pre-embedded vectors.
     *
     * @param  string  $query  The user's question (raw text, not pre-embedded)
     * @param  int<1, max>  $topK  Number of results to return
     * @param  array<string, mixed>  $filters  Metadata filters
     * @return list<QueryResult>
     */
    public function retrieve(string $query, int $topK, array $filters = []): array;
}
