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
        Schema::table('work_orders', function (Blueprint $table) {
            // Nullable and cascade-free, same as `housekeeping_requests.booking_id`:
            // a staff-raised order (no guest involved) has none, and an order
            // still makes sense to maintenance after the booking it came from
            // is gone. Guest-scoped history (Service Request screen) relies on
            // this being exact — a `created_at` time-window comparison ties
            // when a checkout and the next check-in land in the same second.
            $table->foreignId('booking_id')->nullable()->after('room_unit_id')
                ->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booking_id');
        });
    }
};
