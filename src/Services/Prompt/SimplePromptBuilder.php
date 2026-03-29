<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Services\Prompt;

use Thaolaptrinh\Rag\Contracts\PromptBuilder;
use Thaolaptrinh\Rag\Data\QueryResult;

final class SimplePromptBuilder implements PromptBuilder
{
    private readonly float $tokensPerChar;

    public function __construct(float $tokensPerChar = 0.25)
    {
        $this->tokensPerChar = $tokensPerChar;
    }

    public function build(array $context, string $question, int $maxContextTokens): string
    {
        return $this->buildWithSystem(
            'You are a helpful assistant. Answer the question based only on the provided context. If the context does not contain enough information, say so.',
            $context,
            $question,
            $maxContextTokens,
        );
    }

    public function buildWithSystem(string $system, array $context, string $question, int $maxContextTokens): string
    {
        $contextText = $this->buildContextText($context, $maxContextTokens);

        return "System: {$system}\n\nContext:\n{$contextText}\n\nQuestion: {$question}\n\nAnswer:";
    }

    public function estimateTokens(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        return max(0, (int) ceil(strlen($text) * $this->tokensPerChar));
    }

    /**
     * @param  list<QueryResult>  $context
     */
    private function buildContextText(array $context, int $maxTokens): string
    {
        if ($context === []) {
            return 'No context available.';
        }

        $sections = [];
        $usedTokens = 0;

        foreach ($context as $i => $result) {
            $section = "[{$i}] {$result->content}";
            $sectionTokens = $this->estimateTokens($section);

            if ($usedTokens + $sectionTokens > $maxTokens) {
                break;
            }

            $sections[] = $section;
            $usedTokens += $sectionTokens;
        }

        return implode("\n\n", $sections);
    }
}
