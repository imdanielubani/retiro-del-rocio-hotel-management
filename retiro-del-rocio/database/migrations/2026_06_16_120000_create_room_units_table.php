<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('number');                          // e.g. "101"
            $table->string('status')->default('available');    // available | occupied | maintenance
            $table->foreignId('booking_id')->nullable();       // current occupant (no FK constraint to avoid cycles)
            $table->timestamps();

            $table->unique(['room_id', 'number']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('room_unit_id')->nullable()->after('room_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('room_unit_id');
        });
        Schema::dropIfExists('room_units');
    }
};
