<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class RagInstallCommand extends Command
{
    protected $signature = 'rag:install
                            {--without-migration : Skip running migrations}';

    protected $description = 'Install RAG package - publish config and run migrations';

    public function handle(): int
    {
        $this->info('Installing Laravel RAG package...');

        $this->publishConfig();

        $withoutMigration = $this->option('without-migration');

        if (! $withoutMigration) {
            $this->createVectorExtension();
            $this->runMigrations();
        }

        $this->info('Installation complete!');

        return Command::SUCCESS;
    }

    private function publishConfig(): void
    {
        $this->call('vendor:publish', [
            '--provider' => 'Thaolaptrinh\Rag\RagServiceProvider',
            '--tag' => 'rag-config',
        ]);

        $this->info('Config published.');
    }

    private function createVectorExtension(): void
    {
        try {
            $connection = config('rag.database.connection', 'rag');
            /** @var string $connection */
            DB::connection($connection)->statement('CREATE EXTENSION IF NOT EXISTS vector');
            $this->info('pgvector extension ready.');
        } catch (\Throwable $e) {
            $this->warn('Could not create vector extension. Please ensure pgvector is installed: '.$e->getMessage());
        }
    }

    private function runMigrations(): void
    {
        $connection = config('rag.database.connection', 'rag');

        $this->call('migrate', [
            '--database' => $connection,
            '--path' => 'migrations',
            '--force' => true,
        ]);

        $this->info('Migrations complete.');
    }
}
