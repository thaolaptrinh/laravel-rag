<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Tests\Unit\Services\Prompt;

use Thaolaptrinh\Rag\Data\QueryResult;
use Thaolaptrinh\Rag\Services\Prompt\SimplePromptBuilder;

it('estimates tokens for text', function (): void {
    $builder = new SimplePromptBuilder(0.25);

    expect($builder->estimateTokens(''))->toBe(0);
    expect($builder->estimateTokens(str_repeat('a', 400)))->toBe(100);
    expect($builder->estimateTokens(str_repeat('a', 401)))->toBe(101);
});

it('returns no context message for empty context', function (): void {
    $builder = new SimplePromptBuilder;

    $prompt = $builder->build([], 'What is RAG?', 1000);

    expect($prompt)->toContain('No context available');
    expect($prompt)->toContain('What is RAG?');
});

it('builds prompt with context and question', function (): void {
    $builder = new SimplePromptBuilder;

    $context = [
        QueryResult::create('RAG stands for Retrieval-Augmented Generation', 0.95, []),
        QueryResult::create('It combines retrieval and generation', 0.85, []),
    ];

    $prompt = $builder->build($context, 'What is RAG?', 1000);

    expect($prompt)->toContain('System:');
    expect($prompt)->toContain('Context:');
    expect($prompt)->toContain('[0] RAG stands for');
    expect($prompt)->toContain('[1] It combines');
    expect($prompt)->toContain('Question: What is RAG?');
    expect($prompt)->toContain('Answer:');
});

it('builds prompt with custom system message', function (): void {
    $builder = new SimplePromptBuilder;

    $prompt = $builder->buildWithSystem(
        'You are a technical expert.',
        [QueryResult::create('Some context', 0.9, [])],
        'Explain X',
        1000,
    );

    expect($prompt)->toContain('System: You are a technical expert.');
    expect($prompt)->toContain('Context:');
    expect($prompt)->toContain('Some context');
    expect($prompt)->toContain('Question: Explain X');
});

it('truncates context to fit token budget', function (): void {
    $builder = new SimplePromptBuilder(0.25);

    $chunks = [];
    for ($i = 0; $i < 5; $i++) {
        $chunks[] = QueryResult::create(str_repeat('a', 100), 0.9 - ($i * 0.1), []);
    }

    $prompt = $builder->build($chunks, 'Test?', 50);

    $used = 0;
    foreach ($chunks as $chunk) {
        $sectionTokens = $builder->estimateTokens("[0] {$chunk->content}");
        if ($used + $sectionTokens > 50) {
            break;
        }
        $used += $sectionTokens;
    }

    expect($prompt)->toContain('[0]');
});

it('includes only chunks that fit within token budget', function (): void {
    $builder = new SimplePromptBuilder(0.25);

    $chunks = [
        QueryResult::create(str_repeat('a', 100), 0.95, []),
        QueryResult::create(str_repeat('b', 100), 0.85, []),
        QueryResult::create(str_repeat('c', 100), 0.75, []),
    ];

    $prompt = $builder->build($chunks, 'Test?', 30);

    expect($prompt)->toContain('[0]');
    expect($prompt)->not->toContain('[1]');
    expect($prompt)->not->toContain('[2]');
});
