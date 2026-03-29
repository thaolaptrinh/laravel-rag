<?php

declare(strict_types=1);

namespace Thaolaptrinh\Rag\Commands;

use Illuminate\Console\Command;
use Thaolaptrinh\Rag\Exceptions\DocumentNotFoundException;
use Thaolaptrinh\Rag\Rag;

final class RagDeleteCommand extends Command
{
    protected $signature = 'rag:delete
                            {id? : The document ID to delete}
                            {--all : Delete all documents}';

    protected $description = 'Delete documents from the RAG store';

    public function handle(): int
    {
        $all = $this->option('all');

        if ($all) {
            Rag::truncate();
            $this->info('All documents deleted successfully.');

            return Command::SUCCESS;
        }

        $idArg = $this->argument('id');

        if (! is_string($idArg) || $idArg === '') {
            $this->error('Please provide a document ID or use --all to delete all documents.');

            return Command::FAILURE;
        }

        $id = $idArg;

        try {
            Rag::delete($id);
            $this->info("Document '{$id}' deleted successfully.");

            return Command::SUCCESS;
        } catch (DocumentNotFoundException) {
            $this->error("Document '{$id}' not found.");

            return Command::FAILURE;
        }
    }
}
