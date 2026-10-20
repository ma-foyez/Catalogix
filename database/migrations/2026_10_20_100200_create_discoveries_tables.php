<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discoveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('queued');
            $table->unsignedInteger('routes_found')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('discovered_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discovery_id')->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->char('url_hash', 40);
            $table->string('path', 500);
            $table->string('category_name')->nullable();
            $table->string('source', 20);
            $table->unsignedTinyInteger('score')->default(0);
            $table->unsignedInteger('est_products')->default(0);
            $table->json('sample_images')->nullable();
            $table->boolean('is_selected')->default(false);
            $table->timestamps();

            $table->unique(['discovery_id', 'url_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discovered_routes');
        Schema::dropIfExists('discoveries');
    }
};
