<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['discoveries', 'scrape_jobs'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedInteger('max_products')->nullable();
                $table->timestamp('paused_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['discoveries', 'scrape_jobs'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->dropColumn(['max_products', 'paused_at']));
        }
    }
};
