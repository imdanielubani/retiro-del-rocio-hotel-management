<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intercom_calls', function (Blueprint $table) {
            $table->id();

            // Exactly one of {room_unit_id, role} is set on each side — a
            // guest tablet by the room it's bound to, a staff tablet by the
            // role its JWT holds. Labels are cached at call-placement time so
            // the ringing/connected UI never has to re-resolve "who is this"
            // from a guest that may have since checked out.
            $table->foreignId('from_room_unit_id')->nullable()->constrained('room_units')->nullOnDelete();
            $table->string('from_role')->nullable();
            $table->string('from_label');
            $table->string('from_sublabel')->nullable();

            $table->foreignId('to_room_unit_id')->nullable()->constrained('room_units')->nullOnDelete();
            $table->string('to_role')->nullable();
            $table->string('to_label');
            $table->string('to_sublabel')->nullable();

            $table->string('status')->default('ringing');
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intercom_calls');
    }
};
