<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Contracts;

interface Retriever
{
    /**
     * Retrieve relevant chunks for a query
     *
     * @param string $query User query (raw text, not pre-embedded)
     * @param int<1, max> $topK Number of chunks to retrieve
     * @param array<string, mixed> $filters Optional filters for retrieval
     * @return array<int, array{content: string, score: float, metadata: array<string, mixed>}>
     */
    public function retrieve(string $query, int $topK, array $filters = []): array;
}
