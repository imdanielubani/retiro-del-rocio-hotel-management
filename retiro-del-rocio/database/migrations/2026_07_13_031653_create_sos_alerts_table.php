<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Emergency alerts raised from an in-room tablet.
 *
 * The guest's details are *snapshotted* (name, room, suite) rather than only
 * referenced: security must still be able to read "Daniel Ubani, Room 101" from
 * the alert months later, even after the guest checks out and the room is
 * reassigned. An incident record that mutates under you is worthless.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sos_alerts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->foreignId('room_unit_id')->nullable()->constrained('room_units')->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();

            // Snapshot of who and where, as it was at the moment of the alert.
            $table->string('room_number')->nullable();
            $table->string('suite_name')->nullable();
            $table->string('guest_name')->nullable();

            // active → acknowledged (security responding) → resolved
            //        → cancelled (raised in error / stood down by the guest)
            $table->string('status')->default('active')->index();

            $table->timestamp('raised_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('cancelled_by')->nullable(); // 'guest' | 'staff'
            $table->text('notes')->nullable();

            $table->timestamps();

            // The hot query, on both the security tablet and the guest's own:
            // "is anything still open for this room?"
            $table->index(['room_unit_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sos_alerts');
    }
};
