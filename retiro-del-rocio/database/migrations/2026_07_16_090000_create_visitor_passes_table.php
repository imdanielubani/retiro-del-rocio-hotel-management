<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visitor passes a checked-in guest issues from the in-room tablet.
 *
 * The guest invites a visitor by name (+ optional email / WhatsApp) and the
 * system mints a unique 6-digit entry code. Security later verifies or denies
 * the visitor at the gate. As with SOS alerts, the host and room are
 * *snapshotted* so the pass still reads "Daniel Ubani · Room 101" long after the
 * guest checks out and the room is reassigned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitor_passes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->foreignId('room_unit_id')->nullable()->constrained('room_units')->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();

            // Snapshot of the host (guest) and where, as it was when issued.
            $table->string('host_name')->nullable();
            $table->string('room_number')->nullable();
            $table->string('suite_name')->nullable();

            // The invited visitor.
            $table->string('visitor_name');
            $table->string('visitor_email')->nullable();
            $table->string('visitor_phone')->nullable(); // WhatsApp number

            // The 6-digit entry code the visitor quotes at the gate. Unique only
            // among still-open passes (a spent code's digits may be reused later).
            $table->string('code', 6)->index();

            // pending → verified (let in) | denied (turned away)
            //         → cancelled (guest revoked) | expired (timed out)
            $table->string('status')->default('pending')->index();

            $table->timestamp('verified_at')->nullable();
            $table->timestamp('denied_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // The security officer who verified / denied at the gate.
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // The hot query on the guest tablet: "this room's passes, newest first".
            $table->index(['room_unit_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_passes');
    }
};
