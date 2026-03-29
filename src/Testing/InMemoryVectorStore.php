<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Testing;

use Thaolaptrinh\Rag\Contracts\VectorStore;
use Thaolaptrinh\Rag\Data\Chunk;
use Thaolaptrinh\Rag\Data\Document;
use Thaolaptrinh\Rag\Data\QueryResult;

final class InMemoryVectorStore implements VectorStore
{
    /** @var list<Document> */
    private array $documents = [];

    /** @var list<array{chunk: Chunk, embedding: list<float>}> */
    private array $chunks = [];

    public function storeMany(array $items): void
    {
        foreach ($items as $item) {
            $this->chunks[] = $item;
        }
    }

    public function search(array $queryEmbedding, int $topK, array $filters = [], float $minScore = 0.0): array
    {
        $results = [];

        foreach ($this->chunks as $item) {
            $metadata = $item['chunk']->metadata;

            foreach ($filters as $key => $value) {
                $metaValue = $metadata[$key] ?? null;
                if ($metaValue !== $value) {
                    continue 2;
                }
            }

            $score = $this->cosineSimilarity($queryEmbedding, $item['embedding']);

            if ($score >= $minScore) {
                $results[] = QueryResult::create(
                    content: $item['chunk']->content,
                    score: $score,
                    metadata: $metadata,
                );
            }
        }

        usort($results, fn (QueryResult $a, QueryResult $b): int => $b->score <=> $a->score);

        return array_slice($results, 0, $topK);
    }

    /**
     * @param  list<string>  $ids
     * @return list<array{id: string, content_hash: string}>
     */
    public function getDocumentsByIds(array $ids): array
    {
        return array_values(array_filter(
            array_map(fn (Document $doc): array => [
                'id' => $doc->id,
                'content_hash' => $doc->contentHash(),
            ], $this->documents),
            fn (array $result): bool => in_array($result['id'], $ids, true),
        ));
    }

    public function deleteByDocumentId(string $documentId): int
    {
        $count = count(array_filter(
            $this->chunks,
            fn (array $item): bool => $item['chunk']->documentId === $documentId,
        ));

        $this->chunks = array_values(array_filter(
            $this->chunks,
            fn (array $item): bool => $item['chunk']->documentId !== $documentId,
        ));

        $this->documents = array_values(array_filter(
            $this->documents,
            fn (Document $doc): bool => $doc->id !== $documentId,
        ));

        return $count;
    }

    /**
     * @param  list<string>  $documentIds
     */
    public function deleteByDocumentIds(array $documentIds): int
    {
        $count = 0;

        foreach ($documentIds as $id) {
            $count += $this->deleteByDocumentId($id);
        }

        return $count;
    }

    public function truncate(): void
    {
        $this->documents = [];
        $this->chunks = [];
    }

    /**
     * Store a document (for getDocumentsByIds support).
     */
    public function addDocument(Document $document): void
    {
        $this->documents = array_values(array_filter(
            $this->documents,
            fn (Document $doc): bool => $doc->id !== $document->id,
        ));
        $this->documents[] = $document;
    }

    /**
     * @return list<Document>
     */
    public function getDocuments(): array
    {
        return $this->documents;
    }

    /**
     * @return list<Chunk>
     */
    public function getChunks(): array
    {
        return array_map(fn (array $item): Chunk => $item['chunk'], $this->chunks);
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $count = min(count($a), count($b));

        for ($i = 0; $i < $count; $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $denominator = sqrt($normA) * sqrt($normB);

        if ($denominator === 0.0) {
            return 0.0;
        }

        return $dotProduct / $denominator;
    }
}
