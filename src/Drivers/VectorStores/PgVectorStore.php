<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Drivers\VectorStores;

use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Thaolaptrinh\Rag\Contracts\VectorStore;
use Thaolaptrinh\Rag\Data\QueryResult;
use Thaolaptrinh\Rag\Exceptions\StorageFailedException;

final class PgVectorStore implements VectorStore
{
    public function __construct(
        private readonly string $connection = 'rag',
        private readonly string $documentsTable = 'rag_documents',
        private readonly string $chunksTable = 'rag_chunks',
        private readonly int $hnswEfSearch = 100,
    ) {}

    public function storeMany(array $items): void
    {
        if ($items === []) {
            return;
        }

        try {
            $rows = array_map(
                fn (array $item): array => [
                    'document_id' => $item['chunk']->documentId,
                    'content' => $item['chunk']->content,
                    'chunk_index' => $item['chunk']->index,
                    'embedding' => $this->toVectorString($item['embedding']),
                    'metadata' => json_encode($item['chunk']->metadata, JSON_THROW_ON_ERROR),
                ],
                $items,
            );

            $this->db()->table($this->chunksTable)->upsert(
                $rows,
                ['document_id', 'chunk_index'],
                ['content', 'embedding', 'metadata', 'updated_at'],
            );
        } catch (QueryException $e) {
            throw StorageFailedException::create('Failed to store chunks', $e);
        } catch (\JsonException $e) {
            throw StorageFailedException::create('Failed to encode chunk metadata', $e);
        }
    }

    public function search(array $queryEmbedding, int $topK, array $filters = [], float $minScore = 0.0): array
    {
        try {
            return $this->db()->transaction(function () use ($queryEmbedding, $topK, $filters, $minScore): array {
                $this->db()->statement("SET LOCAL hnsw.ef_search = {$this->hnswEfSearch}");

                $vectorStr = $this->toVectorString($queryEmbedding);
                $query = $this->db()->table($this->chunksTable)
                    ->selectRaw('content, metadata, 1 - (embedding <=> ?::vector) AS score', [$vectorStr])
                    ->orderByRaw('embedding <=> ?::vector', [$vectorStr])
                    ->limit($topK);

                foreach ($filters as $key => $value) {
                    $query->whereRaw('metadata->>? = ?', [$key, $value]);
                }

                if ($minScore > 0.0) {
                    $query->having('score', '>=', $minScore);
                }

                $rows = $query->get()->all();

                $results = [];
                foreach ($rows as $row) {
                    $content = (string) $row->content;
                    $score = (float) $row->score;
                    $metadata = json_decode(
                        $row->metadata,
                        true,
                        512,
                        JSON_THROW_ON_ERROR,
                    );

                    if (! is_array($metadata)) {
                        $metadata = [];
                    }

                    $results[] = QueryResult::create($content, $score, $metadata);
                }

                return $results;
            });
        } catch (QueryException $e) {
            throw StorageFailedException::create('Failed to search chunks', $e);
        } catch (\JsonException $e) {
            throw StorageFailedException::create('Failed to decode chunk metadata', $e);
        }
    }

    /**
     * @param  list<string>  $ids
     * @return list<array{id: string, content_hash: string}>
     */
    public function getDocumentsByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        try {
            $rows = $this->db()->table($this->documentsTable)
                ->select('id', 'content_hash')
                ->whereIn('id', $ids)
                ->get()
                ->all();

            $results = [];
            foreach ($rows as $row) {
                $id = (string) $row->id;
                $contentHash = (string) $row->content_hash;

                $results[] = ['id' => $id, 'content_hash' => $contentHash];
            }

            return $results;
        } catch (QueryException $e) {
            throw StorageFailedException::create('Failed to fetch documents', $e);
        }
    }

    public function deleteByDocumentId(string $documentId): int
    {
        try {
            $count = $this->db()->table($this->chunksTable)
                ->where('document_id', $documentId)
                ->delete();

            $this->db()->table($this->documentsTable)
                ->where('id', $documentId)
                ->delete();

            return $count;
        } catch (QueryException $e) {
            throw StorageFailedException::create('Failed to delete document', $e);
        }
    }

    /**
     * @param  list<string>  $documentIds
     */
    public function deleteByDocumentIds(array $documentIds): int
    {
        if ($documentIds === []) {
            return 0;
        }

        try {
            $count = $this->db()->table($this->chunksTable)
                ->whereIn('document_id', $documentIds)
                ->delete();

            $this->db()->table($this->documentsTable)
                ->whereIn('id', $documentIds)
                ->delete();

            return $count;
        } catch (QueryException $e) {
            throw StorageFailedException::create('Failed to delete documents', $e);
        }
    }

    public function truncate(): void
    {
        try {
            $this->db()->table($this->chunksTable)->delete();
            $this->db()->table($this->documentsTable)->delete();
        } catch (QueryException $e) {
            throw StorageFailedException::create('Failed to truncate tables', $e);
        }
    }

    private function db(): Connection
    {
        return DB::connection($this->connection);
    }

    /**
     * @param  list<float>  $embedding
     */
    private function toVectorString(array $embedding): string
    {
        return '['.implode(',', $embedding).']';
    }
}
