<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('source_url')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->char('hash', 40);
            $table->string('name', 500);
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->char('currency', 3)->default('BDT');
            $table->text('product_url')->nullable();
            $table->string('sku')->nullable();
            $table->string('brand')->nullable();
            $table->boolean('in_stock')->nullable();
            $table->json('raw')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'hash']);
            $table->index(['site_id', 'category_id']);
        });

        Schema::create('product_scrape_target', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scrape_target_id')->constrained()->cascadeOnDelete();

            $table->primary(['product_id', 'scrape_target_id']);
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->text('source_url');
            $table->char('url_hash', 40);
            $table->string('local_path', 500)->nullable();
            $table->string('thumb_path', 500)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('bytes')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('error', 500)->nullable();
            $table->timestamps();

            // One image per product; the same source URL is downloaded once and its files reused.
            $table->unique(['product_id', 'url_hash']);
            $table->index('url_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_scrape_target');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
