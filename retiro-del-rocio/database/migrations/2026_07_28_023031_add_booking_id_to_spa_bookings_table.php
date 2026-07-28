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
        Schema::table('spa_bookings', function (Blueprint $table) {
            // Nullable: a website checkout booking (anonymous) has none. A
            // guest-tablet booking sets it, tying the spa charge back to the
            // stay it belongs to — same "soft reference" as reception_notifications.
            $table->foreignId('booking_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booking_id');
        });
    }
};
