<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Contracts;

interface EmbeddingDriver
{
    /**
     * Generate embedding for a single text.
     * Intended for query-time use (single question embedding).
     *
     * @return list<float>
     */
    public function embed(string $text): array;

    /**
     * Generate embeddings for multiple texts (batch).
     * Intended for ingestion — drivers MUST use batch HTTP calls.
     * If input exceeds configured batch_size, split into sequential batch calls.
     * If any batch fails, the entire call throws — partial results are not returned.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedBatch(array $texts): array;

    /**
     * Get the embedding dimension.
     *
     * @return int<1, max>
     */
    public function dimensions(): int;
}
