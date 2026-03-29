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
        DB::connection('rag')->statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('rag_documents', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('content_hash', 64);
            $table->jsonb('metadata')->default('{}');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rag_chunks');
        Schema::dropIfExists('rag_documents');
    }
};
