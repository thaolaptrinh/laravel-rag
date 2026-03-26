<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rag_chunks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('content');
            $table->json('embedding');
            $table->json('metadata')->nullable();
            $table->unsignedInteger('chunk_index')->default(0);
            $table->string('source', 500)->nullable();
            $table->string('type', 100)->default('text');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('deleted_at')->nullable();

            $table->unique(['id', 'deleted_at'], 'unique_chunk_id');
            $table->index(['source', 'deleted_at'], 'idx_source');
            $table->index(['type', 'deleted_at'], 'idx_type');
        });

        DB::statement('CREATE INDEX rag_chunks_embedding_idx ON rag_chunks USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('rag_chunks');
    }
};
