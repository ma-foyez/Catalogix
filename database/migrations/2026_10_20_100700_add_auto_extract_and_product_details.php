<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discoveries', function (Blueprint $table) {
            // Web submissions extract the recommended pages on their own; API clients may still pick routes.
            $table->boolean('auto_extract')->default(false)->after('site_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('subcategory', 190)->nullable()->after('category_id');
            $table->text('description')->nullable()->after('brand');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['subcategory', 'description']);
        });

        Schema::table('discoveries', function (Blueprint $table) {
            $table->dropColumn('auto_extract');
        });
    }
};
