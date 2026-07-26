<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a booking reached us: 'online' (website checkout, the default), 'walk_in'
 * (a guest booked at the front desk) or 'phone'. Lets the admin dashboard and
 * reception flag walk-ins. Existing rows default to 'online'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('source')->default('online')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
