<?php

declare(strict_types=1);

use Thaolaptrinh\Rag\Contracts\LlmDriver;
use Thaolaptrinh\Rag\Contracts\PromptBuilder;
use Thaolaptrinh\Rag\Contracts\Retriever;
use Thaolaptrinh\Rag\Services\Logging\RagLogger;
use Thaolaptrinh\Rag\Services\QueryPipeline;

function makeQueryPipeline(Retriever $retriever, PromptBuilder $builder, LlmDriver $llm): QueryPipeline
{
    $logger = Mockery::mock(RagLogger::class);
    $logger->shouldIgnoreMissing();
    return new QueryPipeline($retriever, $builder, $llm, $logger);
}

test('query returns answer, chunks, and query string', function (): void {
    $chunks = [['content' => 'RAG is great.', 'score' => 0.9, 'metadata' => []]];
    $retriever = Mockery::mock(Retriever::class);
    $retriever->shouldReceive('retrieve')->once()->andReturn($chunks);
    $builder = Mockery::mock(PromptBuilder::class);
    $builder->shouldReceive('build')->once()->andReturn('built prompt');
    $llm = Mockery::mock(LlmDriver::class);
    $llm->shouldReceive('getMaxTokens')->andReturn(4096);
    $llm->shouldReceive('generate')->once()->andReturn('RAG stands for Retrieval-Augmented Generation.');

    $result = makeQueryPipeline($retriever, $builder, $llm)->query('What is RAG?');

    expect($result)->toHaveKeys(['answer', 'chunks', 'query'])
        ->and($result['answer'])->toEqual('RAG stands for Retrieval-Augmented Generation.')
        ->and($result['query'])->toEqual('What is RAG?');
});

test('query passes topK to retriever', function (): void {
    $retriever = Mockery::mock(Retriever::class);
    $retriever->shouldReceive('retrieve')->once()->withArgs(fn ($q, $k) => $k === 10)->andReturn([]);
    $builder = Mockery::mock(PromptBuilder::class);
    $builder->shouldReceive('build')->andReturn('prompt');
    $llm = Mockery::mock(LlmDriver::class);
    $llm->shouldReceive('getMaxTokens')->andReturn(4096);
    $llm->shouldReceive('generate')->andReturn('answer');

    makeQueryPipeline($retriever, $builder, $llm)->query('query', 10);
});

test('query returns answer key when llm throws', function (): void {
    $retriever = Mockery::mock(Retriever::class);
    $retriever->shouldReceive('retrieve')->andReturn([['content' => 'some context', 'score' => 0.9, 'metadata' => []]]);
    $builder = Mockery::mock(PromptBuilder::class);
    $builder->shouldReceive('build')->andReturn('prompt');
    $llm = Mockery::mock(LlmDriver::class);
    $llm->shouldReceive('getMaxTokens')->andReturn(4096);
    $llm->shouldReceive('generate')->andThrow(new \RuntimeException('LLM error'));

    $result = makeQueryPipeline($retriever, $builder, $llm)->query('question');
    expect($result)->toHaveKey('answer');
});
