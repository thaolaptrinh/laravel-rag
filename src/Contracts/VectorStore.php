<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Contracts;

interface VectorStore
{
    /**
     * Store a single chunk with its embedding
     *
     * @param  array{id: string, content: string, metadata?: array<string, mixed>}  $chunk
     * @param  array<int, float>  $embedding  Embedding vector
     */
    public function store(array $chunk, array $embedding): void;

    /**
     * Store multiple chunks with their embeddings (batch operation)
     *
     * @param  array<int, array{chunk: array{id: string, content: string, metadata?: array<string, mixed>}, embedding: array<int, float>}>  $items
     */
    public function storeMany(array $items): void;

    /**
     * Delete a chunk by its ID
     *
     * @param  string  $id  Chunk ID
     */
    public function delete(string $id): void;

    /**
     * Delete multiple chunks by their IDs
     *
     * @param  array<int, string>  $ids  Chunk IDs
     */
    public function deleteMany(array $ids): void;
}
