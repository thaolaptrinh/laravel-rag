<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Tests\Feature\Commands;

use Thaolaptrinh\Rag\Rag;

beforeEach(function (): void {
    Rag::fake();
});

afterEach(function (): void {
    Rag::fake();
});

describe('RagIngestCommand', function (): void {
    it('ingests a document with content', function (): void {
        $this->artisan('rag:ingest', ['content' => 'test document content'])
            ->assertExitCode(0)
            ->expectsOutput('Document ingested successfully.');
    });

    it('accepts optional id', function (): void {
        $this->artisan('rag:ingest', [
            'content' => 'test content',
            '--id' => 'custom-doc-id',
        ])->assertExitCode(0);

        Rag::assertIngested('custom-doc-id');
    });

    it('accepts optional metadata', function (): void {
        $this->artisan('rag:ingest', [
            'content' => 'test content',
            '--metadata' => json_encode(['source' => 'test']),
        ])->assertExitCode(0);
    });
});
