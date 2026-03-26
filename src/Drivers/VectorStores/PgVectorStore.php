<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Drivers\VectorStores;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Thaolaptrinh\Rag\Contracts\VectorStore;
use Thaolaptrinh\Rag\Exceptions\StorageFailedException;

class PgVectorStore implements VectorStore
{
    private readonly string $table;

    public function __construct(string $table = 'rag_chunks')
    {
        $this->table = $table;
    }

    public function store(array $chunk, array $embedding): void
    {
        $this->storeMany([[
            'chunk' => $chunk,
            'embedding' => $embedding,
        ]]);
    }

    public function storeMany(array $items): void
    {
        if (empty($items)) {
            return;
        }

        try {
            DB::beginTransaction();

            foreach ($items as $item) {
                $chunk = $item['chunk'];
                $embedding = $item['embedding'];

                $metadata = $chunk['metadata'] ?? [];
                $source = $metadata['source'] ?? null;
                $type = $metadata['type'] ?? 'text';
                $chunkIndex = $metadata['chunk_index'] ?? 0;

                try {
                    DB::table($this->table)->insert([
                        'id' => $chunk['id'],
                        'content' => $chunk['content'],
                        'embedding' => json_encode($embedding, JSON_THROW_ON_ERROR),
                        'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                        'chunk_index' => $chunkIndex,
                        'source' => $source,
                        'type' => $type,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (QueryException $e) {
                    $errorCode = $e->errorInfo[1] ?? null;

                    if ($errorCode === 23505) {
                        continue;
                    }

                    throw new StorageFailedException(
                        "Failed to store chunk {$chunk['id']}: ".$e->getMessage(),
                        $e->getCode(),
                        $e
                    );
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            if ($e instanceof StorageFailedException) {
                throw $e;
            }

            throw new StorageFailedException(
                'Batch store failed: '.$e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    public function delete(string $id): void
    {
        try {
            DB::table($this->table)
                ->where('id', $id)
                ->update(['deleted_at' => now()]);
        } catch (\Exception $e) {
            throw new StorageFailedException(
                "Failed to delete chunk {$id}: ".$e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    public function deleteMany(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        try {
            DB::table($this->table)
                ->whereIn('id', $ids)
                ->update(['deleted_at' => now()]);
        } catch (\Exception $e) {
            throw new StorageFailedException(
                'Failed to delete chunks: '.$e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
}
