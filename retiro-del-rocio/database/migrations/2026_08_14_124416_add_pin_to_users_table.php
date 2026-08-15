<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Hashed 4-digit tablet-login PIN — an alternative to password
            // for staff signing in on a station tablet. Nullable: an
            // account has no PIN until a manager/admin/super-admin sets
            // one (see Users & Staff → Reset Credentials).
            $table->string('pin')->nullable()->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pin');
        });
    }
};
