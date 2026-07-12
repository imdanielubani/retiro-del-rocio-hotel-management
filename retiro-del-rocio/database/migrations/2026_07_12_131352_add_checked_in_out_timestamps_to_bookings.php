<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bookings store arrival/departure as dates, and the *expected* time comes from
 * the hotel's policy (Settings → Hotel Info). That policy is editable, so it
 * cannot also stand in for what actually happened: these record the real moment
 * reception checked the guest in and out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('checked_in_at')->nullable()->after('paid_at');
            $table->timestamp('checked_out_at')->nullable()->after('checked_in_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['checked_in_at', 'checked_out_at']);
        });
    }
};
