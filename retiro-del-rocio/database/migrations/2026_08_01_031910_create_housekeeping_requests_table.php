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
        Schema::create('housekeeping_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_unit_id')->constrained()->cascadeOnDelete();
            // Nullable and cascade-free: the request still makes sense to
            // housekeeping after the booking it refers to is gone.
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // towels | amenities | dnd | make_up_room | other
            $table->text('notes')->nullable();
            $table->string('status')->default('pending'); // pending | completed
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('housekeeping_requests');
    }
};
