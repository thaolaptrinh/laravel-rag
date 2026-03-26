<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Services\Retrievers;

use Illuminate\Support\Facades\DB;
use Thaolaptrinh\Rag\Contracts\EmbeddingDriver;
use Thaolaptrinh\Rag\Contracts\Retriever;
use Thaolaptrinh\Rag\Exceptions\RetrievalFailedException;

class SimilarityRetriever implements Retriever
{
    private readonly EmbeddingDriver $embedder;

    private readonly string $table;

    public function __construct(
        EmbeddingDriver $embedder,
        string $table = 'rag_chunks'
    ) {
        $this->embedder = $embedder;
        $this->table = $table;
    }

    /**
     * Retrieve relevant chunks for a query
     *
     * @param  string  $query  User query (raw text, not pre-embedded)
     * @param  int<1, max>  $topK  Number of chunks to retrieve
     * @param  array<string, mixed>  $filters  Optional filters for retrieval
     * @return array<int, array{content: string, score: float, metadata: array<string, mixed>}>
     */
    public function retrieve(string $query, int $topK, array $filters = []): array
    {
        try {
            $queryEmbedding = $this->embedder->embed($query);

            $results = DB::table($this->table)
                ->select('content', 'metadata')
                ->selectRaw(
                    '1 - (embedding <=> ?::vector) as score',
                    [$this->vectorToArrayString($queryEmbedding)]
                )
                ->whereNull('deleted_at')
                ->orderBy('score', 'desc')
                ->limit($topK);

            foreach ($filters as $key => $value) {
                $results->where($key, $value);
            }

            $rows = $results->get();

            $chunks = [];
            foreach ($rows as $row) {
                $metadata = json_decode($row->metadata ?? '{}', true, 512, JSON_THROW_ON_ERROR);
                $chunks[] = [
                    'content' => $row->content,
                    'score' => (float) $row->score,
                    'metadata' => $metadata,
                ];
            }

            return $chunks;
        } catch (\Exception $e) {
            throw new RetrievalFailedException(
                'Retrieval failed: '.$e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Convert embedding vector to PostgreSQL array string
     *
     * @param  array<int, float>  $embedding
     */
    private function vectorToArrayString(array $embedding): string
    {
        return '['.implode(',', $embedding).']';
    }
}
