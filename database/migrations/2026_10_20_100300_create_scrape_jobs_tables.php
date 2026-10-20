<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scrape_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discovery_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('queued');
            $table->unsignedInteger('total_routes')->default(0);
            $table->unsignedInteger('total_products')->default(0);
            $table->unsignedInteger('total_images')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('scrape_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scrape_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discovered_route_id')->nullable()->constrained()->nullOnDelete();
            $table->text('url');
            $table->string('status', 20)->default('queued');
            $table->string('strategy_used', 30)->nullable();
            $table->unsignedInteger('pages_crawled')->default(0);
            $table->unsignedInteger('products_found')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index('scrape_job_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrape_targets');
        Schema::dropIfExists('scrape_jobs');
    }
};
