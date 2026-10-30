<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entity', 30);
            $table->string('original_filename');
            $table->string('stored_path');
            $table->char('file_hash', 64);
            $table->string('status', 20)->default('uploaded');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('ready_rows')->default(0);
            $table->unsignedInteger('warning_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->json('headers')->nullable();
            $table->json('header_map')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'entity', 'created_at']);
            $table->index(['tenant_id', 'file_hash']);
            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('import_batch_id');
            $table->unsignedInteger('row_number');
            $table->string('status', 20);
            $table->json('payload');
            $table->json('errors')->nullable();
            $table->json('warnings')->nullable();
            $table->timestamps();
            $table->foreign(['tenant_id', 'import_batch_id'])->references(['tenant_id', 'id'])->on('import_batches')->cascadeOnDelete();
            $table->unique(['tenant_id', 'import_batch_id', 'row_number']);
            $table->index(['tenant_id', 'import_batch_id', 'status', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_batches');
    }
};
