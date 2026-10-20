<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discoveries', function (Blueprint $table) {
            $table->string('stage', 20)->default('waiting')->after('status');
            $table->unsignedInteger('candidates')->default(0)->after('stage');
            $table->unsignedSmallInteger('probed')->default(0)->after('candidates');
            $table->unsignedSmallInteger('to_probe')->default(0)->after('probed');
        });

        DB::table('discoveries')->whereIn('status', ['done', 'failed'])->update(['stage' => 'finished']);
    }

    public function down(): void
    {
        Schema::table('discoveries', function (Blueprint $table) {
            $table->dropColumn(['stage', 'candidates', 'probed', 'to_probe']);
        });
    }
};
