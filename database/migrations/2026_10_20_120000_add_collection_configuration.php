<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->json('collection_settings')->nullable());
        foreach (['discoveries', 'scrape_jobs'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedInteger('min_products')->default(1);
                $table->unsignedSmallInteger('max_pages')->default(30);
                $table->unsignedSmallInteger('max_routes')->default(25);
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('collection_settings'));
        foreach (['discoveries', 'scrape_jobs'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['min_products', 'max_pages', 'max_routes']));
        }
    }
};
