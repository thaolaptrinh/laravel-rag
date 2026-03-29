<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Services\Pipelines;

use Illuminate\Support\Str;
use Thaolaptrinh\Rag\Contracts\LlmDriver;
use Thaolaptrinh\Rag\Contracts\PromptBuilder;
use Thaolaptrinh\Rag\Contracts\Retriever;
use Thaolaptrinh\Rag\Data\Answer;
use Thaolaptrinh\Rag\Services\RagLogger;

final class QueryPipeline
{
    public function __construct(
        private readonly Retriever $retriever,
        private readonly PromptBuilder $promptBuilder,
        private readonly LlmDriver $llmDriver,
        private readonly RagLogger $logger,
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public function run(string $question, array $options = []): Answer
    {
        $traceId = Str::uuid()->toString();
        $startTime = microtime(true);

        /** @var int<1, max> */
        $topK = $options['top_k'] ?? 20;
        /** @var array<string, mixed> */
        $filters = $options['filters'] ?? [];
        $system = $options['system'] ?? null;

        $this->logger->queryStart($traceId, $question);

        $context = $this->retriever->retrieve($question, $topK, $filters);

        $maxContextTokens = $this->llmDriver->getContextWindow() - $this->llmDriver->getMaxOutputTokens();
        $maxContextTokens = max(1, $maxContextTokens - 500);

        $prompt = is_string($system)
            ? $this->promptBuilder->buildWithSystem($system, $context, $question, $maxContextTokens)
            : $this->promptBuilder->build($context, $question, $maxContextTokens);

        $systemPrompt = is_string($system) ? $system : 'You are a helpful assistant. Answer the question based only on the provided context. If the context does not contain enough information, say so.';
        $answerText = $this->llmDriver->generateWithSystem($systemPrompt, $prompt);

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        $this->logger->queryComplete($traceId, count($context), $durationMs);

        return Answer::create($answerText, $context, $traceId);
    }

    /**
     * @param  callable(string): void  $callback
     * @param  array<string, mixed>  $options
     */
    public function runStream(string $question, callable $callback, array $options = []): Answer
    {
        $traceId = Str::uuid()->toString();
        $startTime = microtime(true);

        /** @var int<1, max> */
        $topK = $options['top_k'] ?? 20;
        /** @var array<string, mixed> */
        $filters = $options['filters'] ?? [];
        $system = $options['system'] ?? null;

        $this->logger->queryStart($traceId, $question);

        $context = $this->retriever->retrieve($question, $topK, $filters);

        $maxContextTokens = $this->llmDriver->getContextWindow() - $this->llmDriver->getMaxOutputTokens();
        $maxContextTokens = max(1, $maxContextTokens - 500);

        $prompt = is_string($system)
            ? $this->promptBuilder->buildWithSystem($system, $context, $question, $maxContextTokens)
            : $this->promptBuilder->build($context, $question, $maxContextTokens);

        $systemPrompt = is_string($system) ? $system : 'You are a helpful assistant. Answer the question based only on the provided context. If the context does not contain enough information, say so.';
        $this->llmDriver->generateStreamWithSystem($systemPrompt, $prompt, $callback);

        $durationMs = (int) ((microtime(true) - $startTime) * 1000);

        $this->logger->queryComplete($traceId, count($context), $durationMs);

        return Answer::create('', $context, $traceId);
    }
}
