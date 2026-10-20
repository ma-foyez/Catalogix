<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Waiting on pause or a domain lock is not a failure. A long pause can exceed 255 releases.
        Schema::table('jobs', fn (Blueprint $table) => $table->unsignedInteger('attempts')->change());
    }

    public function down(): void
    {
        // Keep the wider counter: narrowing it could truncate attempts on an existing queued job.
    }
};
