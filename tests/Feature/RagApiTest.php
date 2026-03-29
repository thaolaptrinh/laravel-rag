<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Tests\Feature;

use Thaolaptrinh\Rag\Data\Document;
use Thaolaptrinh\Rag\Rag;

describe('Rag API', function (): void {
    beforeEach(function (): void {
        Rag::fake();
    });

    afterEach(function (): void {
        Rag::fake();
    });
    describe('ingest', function (): void {
        it('ingests a single document', function (): void {
            $document = Document::create('test content', ['source' => 'test']);

            $result = Rag::ingest($document);

            expect($result->ingested)->toBe(1);
            expect($result->skipped)->toBe(0);
            expect($result->errors)->toBe(0);
            expect($result->traceId)->not->toBeEmpty();
        });

        it('tracks document id in fake mode', function (): void {
            $document = Document::create('test content', [], 'doc-123');

            Rag::ingest($document);

            Rag::assertIngested('doc-123');
            expect(true)->toBeTrue();
        });
    });

    describe('ingestMany', function (): void {
        it('ingests multiple documents', function (): void {
            $documents = [
                Document::create('content 1', [], 'doc-1'),
                Document::create('content 2', [], 'doc-2'),
            ];

            $result = Rag::ingestMany($documents);

            expect($result->ingested)->toBe(2);
            expect($result->skipped)->toBe(0);
            expect($result->errors)->toBe(0);
        });
    });

    describe('query', function (): void {
        it('queries the rag store', function (): void {
            $answer = Rag::query('What is this about?');

            expect($answer->text)->toBe('Fake response');
            expect($answer->sources)->toBeEmpty();
            expect($answer->traceId)->not->toBeEmpty();
        });

        it('tracks question in fake mode', function (): void {
            Rag::query('What is Laravel?');

            Rag::assertQueried('What is Laravel?');
            expect(true)->toBeTrue();
        });

        it('accepts options', function (): void {
            $answer = Rag::query('Test question?', [
                'top_k' => 5,
                'filters' => ['source' => 'pdf'],
            ]);

            expect($answer->text)->toBe('Fake response');
        });
    });

    describe('delete', function (): void {
        it('deletes a document', function (): void {
            $document = Document::create('content', [], 'doc-123');
            Rag::ingest($document);

            Rag::delete('doc-123');

            Rag::assertDeleted('doc-123');
            expect(true)->toBeTrue();
        });
    });

    describe('deleteMany', function (): void {
        it('deletes multiple documents', function (): void {
            $doc1 = Document::create('content 1', [], 'doc-1');
            $doc2 = Document::create('content 2', [], 'doc-2');
            Rag::ingestMany([$doc1, $doc2]);

            Rag::deleteMany(['doc-1', 'doc-2']);

            Rag::assertDeleted('doc-1');
            Rag::assertDeleted('doc-2');
            expect(true)->toBeTrue();
        });
    });

    describe('truncate', function (): void {
        it('truncates all documents', function (): void {
            Rag::truncate();
            expect(true)->toBeTrue();
        });
    });

    describe('fake', function (): void {
        it('can reset fake by calling again', function (): void {
            Rag::fake();

            Rag::ingest(Document::create('content', [], 'reset-test'));

            Rag::assertIngested('reset-test');
            expect(true)->toBeTrue();
        });
    });
});
