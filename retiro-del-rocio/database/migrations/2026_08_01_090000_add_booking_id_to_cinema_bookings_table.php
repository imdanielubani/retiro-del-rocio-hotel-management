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
        Schema::table('cinema_bookings', function (Blueprint $table) {
            // Nullable: a website checkout booking (anonymous) has none. A
            // guest-tablet booking sets it, tying the cinema charge back to the
            // stay it belongs to — same pattern as spa_bookings.booking_id.
            $table->foreignId('booking_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cinema_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booking_id');
        });
    }
};
