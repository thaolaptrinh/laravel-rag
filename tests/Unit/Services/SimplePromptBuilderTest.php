<?php

declare(strict_types=1);

use Thaolaptrinh\Rag\Services\SimplePromptBuilder;

test('build returns a non-empty string containing query and context', function (): void {
    $builder = new SimplePromptBuilder();
    $chunks = [['content' => 'RAG stands for Retrieval-Augmented Generation.', 'score' => 0.92, 'metadata' => []]];
    $prompt = $builder->build($chunks, 'What is RAG?', 4000);
    expect($prompt)->toBeString()->not->toBeEmpty()
        ->and($prompt)->toContain('What is RAG?')
        ->and($prompt)->toContain('RAG stands for');
});

test('build respects maxTokens limit by truncating chunks', function (): void {
    $builder = new SimplePromptBuilder();
    $chunks = array_fill(0, 100, ['content' => str_repeat('word ', 200), 'score' => 0.9, 'metadata' => []]);
    $prompt = $builder->build($chunks, 'query', 200);
    expect(strlen($prompt))->toBeLessThan(20000);
});

test('buildWithSystem includes system prompt in output', function (): void {
    $builder = new SimplePromptBuilder();
    $prompt = $builder->buildWithSystem(
        'You are a PHP expert.',
        [['content' => 'PHP is great.', 'score' => 0.9, 'metadata' => []]],
        'Tell me about PHP',
        4000
    );
    expect($prompt)->toContain('You are a PHP expert.');
});

test('estimateTokens returns a positive integer', function (): void {
    $builder = new SimplePromptBuilder();
    $tokens = $builder->estimateTokens(implode(' ', array_fill(0, 100, 'word')));
    expect($tokens)->toBeGreaterThan(0);
});
