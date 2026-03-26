<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Services;

use Thaolaptrinh\Rag\Contracts\PromptBuilder;

class SimplePromptBuilder implements PromptBuilder
{
    private readonly string $defaultSystem;

    private readonly int $averageTokensPerWord;

    public function __construct(
        string $defaultSystem = 'You are a helpful assistant. Answer the question based on the provided context.',
        int $averageTokensPerWord = 4
    ) {
        $this->defaultSystem = $defaultSystem;
        $this->averageTokensPerWord = $averageTokensPerWord;
    }

    public function build(array $context, string $query, int $maxTokens): string
    {
        return $this->buildWithSystem($this->defaultSystem, $context, $query, $maxTokens);
    }

    public function buildWithSystem(string $system, array $context, string $query, int $maxTokens): string
    {
        $systemTokens = $this->estimateTokens($system);
        $queryTokens = $this->estimateTokens($query);
        $availableForContext = $maxTokens - $systemTokens - $queryTokens - 100;

        if ($availableForContext <= 0) {
            return $this->buildPrompt($system, '', $query);
        }

        $contextText = $this->buildContextText($context, $availableForContext);

        return $this->buildPrompt($system, $contextText, $query);
    }

    public function estimateTokens(string $text): int
    {
        $wordCount = str_word_count($text);

        return (int) ceil($wordCount / $this->averageTokensPerWord);
    }

    /**
     * Build context text from chunks, respecting token limit
     *
     * @param  array<int, array{content: string, score: float, metadata: array<string, mixed>}>  $context
     * @param  int  $maxTokens  Maximum tokens for context
     */
    private function buildContextText(array $context, int $maxTokens): string
    {
        $chunks = [];
        $totalTokens = 0;

        foreach ($context as $item) {
            $chunkTokens = $this->estimateTokens($item['content']);

            if ($totalTokens + $chunkTokens > $maxTokens) {
                break;
            }

            $chunks[] = "[Source: {$item['score']}] {$item['content']}";
            $totalTokens += $chunkTokens;
        }

        return implode("\n\n", $chunks);
    }

    /**
     * Build final prompt
     */
    private function buildPrompt(string $system, string $context, string $query): string
    {
        $prompt = "System: {$system}\n\n";

        if (! empty($context)) {
            $prompt .= "Context:\n{$context}\n\n";
        }

        $prompt .= "Question: {$query}\n\nAnswer:";

        return $prompt;
    }
}
