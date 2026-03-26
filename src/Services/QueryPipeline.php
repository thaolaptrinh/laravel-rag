<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Services;

use Thaolaptrinh\Rag\Contracts\LlmDriver;
use Thaolaptrinh\Rag\Contracts\PromptBuilder;
use Thaolaptrinh\Rag\Contracts\Retriever;
use Thaolaptrinh\Rag\Services\Logging\RagLogger;

class QueryPipeline
{
    public function __construct(
        private readonly Retriever $retriever,
        private readonly PromptBuilder $promptBuilder,
        private readonly LlmDriver $llm,
        private readonly RagLogger $logger
    ) {}

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
        $this->logger->queryStart($query);

        try {
            $chunks = $this->retriever->retrieve($query, $topK, $filters);

            if (empty($chunks)) {
                $this->logger->queryComplete($query, 0);

                return [
                    'answer' => 'No relevant information found.',
                    'chunks' => 0,
                    'query' => $query,
                ];
            }

            $maxTokens = $this->llm->getMaxTokens();
            $prompt = $this->promptBuilder->build($chunks, $query, $maxTokens);

            $answer = $this->llm->generate($prompt);

            $this->logger->queryComplete($query, count($chunks));

            return [
                'answer' => $answer,
                'chunks' => count($chunks),
                'query' => $query,
            ];
        } catch (\Exception $e) {
            $this->logger->queryError($query, $e->getMessage());

            return [
                'answer' => 'Query failed: '.$e->getMessage(),
                'chunks' => 0,
                'query' => $query,
            ];
        }
    }
}
