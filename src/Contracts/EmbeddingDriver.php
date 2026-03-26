<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Contracts;

interface EmbeddingDriver
{
    /**
     * Generate embedding for a single text
     *
     * @param string $text Text to embed
     * @return array<int, float> Embedding vector
     */
    public function embed(string $text): array;

    /**
     * Generate embeddings for multiple texts (batch operation)
     *
     * @param array<int, string> $texts Texts to embed
     * @return array<int, array<int, float>> Array of embedding vectors
     */
    public function embedBatch(array $texts): array;

    /**
     * Get the embedding dimension
     *
     * @return int<1, max>
     */
    public function getDimension(): int;
}
