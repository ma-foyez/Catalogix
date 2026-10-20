<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scrape_job_id')->nullable()->constrained()->nullOnDelete();
            $table->json('scope');
            $table->string('format', 20);
            $table->string('layout', 20)->default('per_site');
            $table->string('file_path', 500)->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->string('status', 20)->default('pending');
            $table->text('error')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};
