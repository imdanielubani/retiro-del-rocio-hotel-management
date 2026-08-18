<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A named group of smart-device actions a guest can trigger in one tap (e.g.
 * "Welcome", "Relax", "Sleep"). Exactly one of `room_id` / `room_unit_id` is
 * set per row (category-level template vs. a room-specific override/addition
 * — enforced in the model's `creating` hook, not a DB CHECK, since SQLite is
 * the local/test driver).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smart_scenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('room_unit_id')->nullable()->constrained('room_units')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug'); // e.g. welcome, relax, sleep, checkout — unique per (room_id, room_unit_id)
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_scenes');
    }
};
