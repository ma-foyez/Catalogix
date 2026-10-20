<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->string('name')->nullable();
            $table->string('platform', 20)->default('unknown');
            $table->boolean('needs_js')->default(false);
            $table->string('strategy', 30)->nullable();
            $table->json('selectors')->nullable();
            $table->json('pagination')->nullable();
            $table->boolean('recipe_valid')->default(false);
            $table->boolean('is_blocked')->default(false);
            $table->string('blocked_reason', 500)->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
