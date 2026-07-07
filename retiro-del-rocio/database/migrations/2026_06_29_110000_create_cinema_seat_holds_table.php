<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cinema_seat_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movie_id')->constrained()->cascadeOnDelete();
            $table->date('show_date');
            $table->string('show_time', 30);
            $table->string('seat', 8);
            $table->string('token', 80);
            $table->foreignId('cinema_booking_id')->nullable()->constrained()->nullOnDelete();
            // NULL once the seat is booked (permanent); otherwise a temporary hold.
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // One row per seat per showing — the hard lock against double-booking.
            $table->unique(['movie_id', 'show_date', 'show_time', 'seat'], 'cinema_seat_unique');
            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cinema_seat_holds');
    }
};
