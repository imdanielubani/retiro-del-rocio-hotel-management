<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a verified visitor leaves. Lets the admin register distinguish visitors
 * who are still inside from those who have gone, and drives the "Currently
 * inside" counter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitor_passes', function (Blueprint $table) {
            $table->timestamp('exited_at')->nullable()->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('visitor_passes', function (Blueprint $table) {
            $table->dropColumn('exited_at');
        });
    }
};
