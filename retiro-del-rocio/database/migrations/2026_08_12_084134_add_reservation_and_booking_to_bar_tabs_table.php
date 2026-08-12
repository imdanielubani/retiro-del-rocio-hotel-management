<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A tab can now originate from a confirmed table/lounge reservation
     * (the waiter looks up the reservation ID and pushes it straight into a
     * tab), and can be settled with "Charge to Room" against an in-house
     * guest's booking — both previously impossible, since a `BarTab` had no
     * link to either.
     */
    public function up(): void
    {
        Schema::table('bar_tabs', function (Blueprint $table) {
            $table->foreignId('restaurant_reservation_id')->nullable()->after('id')
                ->constrained('restaurant_reservations')->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->after('restaurant_reservation_id')
                ->constrained('bookings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bar_tabs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('restaurant_reservation_id');
            $table->dropConstrainedForeignId('booking_id');
        });
    }
};
