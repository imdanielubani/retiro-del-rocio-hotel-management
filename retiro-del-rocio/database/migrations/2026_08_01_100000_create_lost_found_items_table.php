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
        Schema::create('lost_found_items', function (Blueprint $table) {
            $table->id();
            // Where it turned up, and whose stay it was found during — both
            // nullable: a housekeeper may log an item found in a corridor or
            // common area with no room, or against an already-departed guest
            // whose booking has since been purged.
            $table->foreignId('room_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_description');
            $table->text('notes')->nullable();
            $table->foreignId('found_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('found_at');
            $table->string('status')->default('unclaimed'); // unclaimed | returned | disposed
            // Who the item belongs to / was handed back to — not necessarily
            // the booking's own guest (a repeat visitor, a walk-in claim).
            $table->string('claimant_name')->nullable();
            $table->string('claimant_contact')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lost_found_items');
    }
};
