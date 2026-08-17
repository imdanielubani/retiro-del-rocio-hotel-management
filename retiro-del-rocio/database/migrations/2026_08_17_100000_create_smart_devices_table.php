<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per physical Tuya (or future provider) smart-room device — lights,
 * AC, curtains, TVs. Assigned to exactly one `room_units` row; discovered but
 * unassigned devices are kept with a null `room_unit_id` until an admin
 * places them (never auto-assigned). See docs/architecture/02-smart-room-architecture.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smart_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_unit_id')->nullable()->constrained('room_units')->nullOnDelete();
            $table->string('name');
            $table->string('type')->index(); // light | ac | curtain | tv — open string, not enum-locked
            $table->string('provider')->default('tuya');
            $table->string('provider_device_id')->unique();
            $table->string('provider_product_id')->nullable();
            $table->json('capabilities')->nullable(); // normalized capability map, cached from Tuya's spec
            $table->json('last_state')->nullable();   // last known DP values
            $table->string('status')->default('unknown'); // online | offline | unknown
            $table->timestamp('last_synced_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_devices');
    }
};
