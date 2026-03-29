<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Tests\Feature\Commands;

use Thaolaptrinh\Rag\Data\Document;
use Thaolaptrinh\Rag\Rag;

beforeEach(function (): void {
    Rag::fake();
});

afterEach(function (): void {
    Rag::fake();
});

describe('RagDeleteCommand', function (): void {
    it('deletes a document by id', function (): void {
        Rag::ingest(Document::create('content', [], 'doc-123'));

        $this->artisan('rag:delete', ['id' => 'doc-123'])
            ->assertExitCode(0)
            ->expectsOutput("Document 'doc-123' deleted successfully.");

        Rag::assertDeleted('doc-123');
    });

    it('deletes all documents with --all', function (): void {
        $this->artisan('rag:delete', ['--all' => true])
            ->assertExitCode(0)
            ->expectsOutput('All documents deleted successfully.');
    });

    it('fails without id or --all', function (): void {
        $this->artisan('rag:delete')
            ->assertExitCode(1)
            ->expectsOutput('Please provide a document ID or use --all to delete all documents.');
    });
});
