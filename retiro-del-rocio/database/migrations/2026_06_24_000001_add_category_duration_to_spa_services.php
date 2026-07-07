<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spa_services', function (Blueprint $table) {
            $table->foreignId('spa_category_id')->nullable()->after('slug')->constrained()->nullOnDelete();
            $table->unsignedInteger('duration_minutes')->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('spa_services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('spa_category_id');
            $table->dropColumn('duration_minutes');
        });
    }
};
