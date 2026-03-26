<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Contracts;

interface PromptBuilder
{
    /**
     * Build a prompt from context and query
     *
     * @param array<int, array{content: string, score: float, metadata: array<string, mixed>}> $context Retrieved chunks
     * @param string $query User query
     * @param int<1, max> $maxTokens Maximum tokens for the prompt
     * @return string Built prompt
     */
    public function build(array $context, string $query, int $maxTokens): string;

    /**
     * Build a prompt with system instructions
     *
     * @param string $system System instructions
     * @param array<int, array{content: string, score: float, metadata: array<string, mixed>}> $context Retrieved chunks
     * @param string $query User query
     * @param int<1, max> $maxTokens Maximum tokens for the prompt
     * @return string Built prompt
     */
    public function buildWithSystem(string $system, array $context, string $query, int $maxTokens): string;

    /**
     * Estimate token count for a string
     *
     * @param string $text Text to estimate
     * @return int<0, max> Estimated token count
     */
    public function estimateTokens(string $text): int;
}
