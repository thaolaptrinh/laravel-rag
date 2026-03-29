<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Contracts;

use Thaolaptrinh\Rag\Data\QueryResult;

interface PromptBuilder
{
    /**
     * Build prompt from context and question.
     *
     * @param  list<QueryResult>  $context
     * @param  int<1, max>  $maxContextTokens  Token budget for context
     */
    public function build(array $context, string $question, int $maxContextTokens): string;

    /**
     * Build prompt with custom system instructions.
     *
     * @param  list<QueryResult>  $context
     */
    public function buildWithSystem(string $system, array $context, string $question, int $maxContextTokens): string;

    /**
     * Estimate token count for text.
     *
     * @return int<0, max>
     */
    public function estimateTokens(string $text): int;
}
