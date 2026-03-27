<?php

declare(strict_types=1);

use Thaolaptrinh\Rag\Drivers\DataSource\TextDataSource;

test('loads text file and returns document structure', function (): void {
    $source = new TextDataSource();
    $docs = $source->load(__DIR__.'/../../fixtures/sample.txt');

    expect($docs)->toBeArray()
        ->and($docs)->toHaveCount(1)
        ->and($docs[0])->toHaveKeys(['id', 'content', 'metadata'])
        ->and($docs[0]['id'])->toBeString()
        ->and($docs[0]['content'])->toContain('RAG systems')
        ->and($docs[0]['metadata']['source'])->toEqual(__DIR__.'/../../fixtures/sample.txt');
});

test('each call returns a unique document id', function (): void {
    $source = new TextDataSource();
    $doc1 = $source->load(__DIR__.'/../../fixtures/sample.txt');
    $doc2 = $source->load(__DIR__.'/../../fixtures/sample.txt');
    expect($doc1[0]['id'])->not->toEqual($doc2[0]['id']);
});

test('throws when file does not exist', function (): void {
    $source = new TextDataSource();
    expect(fn () => $source->load('/tmp/does-not-exist-xyz-abc.txt'))
        ->toThrow(\Exception::class);
});
