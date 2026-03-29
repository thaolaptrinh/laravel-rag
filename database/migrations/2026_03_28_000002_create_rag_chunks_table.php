<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'rag';

    public function up(): void
    {
        Schema::connection('rag')->create('rag_chunks', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('document_id');
            $table->text('content');
            $table->integer('chunk_index');
            $table->vector('embedding');
            $table->jsonb('metadata')->default('{}');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->foreign('document_id')
                ->references('id')
                ->on('rag_documents')
                ->onDelete('cascade');

            $table->unique(['document_id', 'chunk_index'], 'uq_chunks_document_chunk');
        });

        DB::connection('rag')->statement("ALTER TABLE rag_chunks ADD COLUMN content_tsv TSVECTOR GENERATED ALWAYS AS (to_tsvector('english', content)) STORED");
        DB::connection('rag')->statement('ALTER TABLE rag_chunks ADD CONSTRAINT chk_chunk_index CHECK (chunk_index >= 0)');
    }

    public function down(): void
    {
        Schema::connection('rag')->dropIfExists('rag_chunks');
    }
};
