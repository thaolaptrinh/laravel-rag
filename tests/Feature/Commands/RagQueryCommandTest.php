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

describe('RagQueryCommand', function (): void {
    it('queries the rag store', function (): void {
        $this->artisan('rag:query', ['question' => 'What is Laravel?'])
            ->assertExitCode(0)
            ->expectsOutput('Answer:')
            ->expectsOutput('Fake response');
    });

    it('accepts optional top-k', function (): void {
        $this->artisan('rag:query', [
            'question' => 'Test?',
            '--top-k' => '10',
        ])->assertExitCode(0);
    });

    it('accepts optional filters', function (): void {
        $this->artisan('rag:query', [
            'question' => 'Test?',
            '--filters' => json_encode(['source' => 'pdf']),
        ])->assertExitCode(0);
    });
});
