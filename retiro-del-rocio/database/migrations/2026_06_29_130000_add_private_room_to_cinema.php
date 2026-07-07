<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Flat price to book a whole private 4-seat room.
        Schema::table('movies', function (Blueprint $table) {
            $table->unsignedInteger('room_price')->default(40000)->after('child_price');
        });

        // Which private room was booked, and how many guests (up to the room's 4 seats).
        Schema::table('cinema_bookings', function (Blueprint $table) {
            $table->string('room')->nullable()->after('show_time');     // "Room 1" | "Room 2"
            $table->unsignedSmallInteger('guests')->default(1)->after('room');
        });
    }

    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->dropColumn('room_price');
        });
        Schema::table('cinema_bookings', function (Blueprint $table) {
            $table->dropColumn(['room', 'guests']);
        });
    }
};
