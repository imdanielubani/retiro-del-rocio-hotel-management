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
        Schema::table('room_units', function (Blueprint $table) {
            // Deliberately separate from the existing `status` column, which
            // tracks occupancy (available | occupied | maintenance) — a room
            // can be occupied and dirty, or available and dirty, at the same
            // time. clean | dirty | inspected | out_of_order.
            $table->string('housekeeping_status')->default('clean')->after('status');
            $table->timestamp('housekeeping_status_at')->nullable()->after('housekeeping_status');

            $table->index('housekeeping_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('room_units', function (Blueprint $table) {
            $table->dropIndex(['housekeeping_status']);
            $table->dropColumn(['housekeeping_status', 'housekeeping_status_at']);
        });
    }
};
