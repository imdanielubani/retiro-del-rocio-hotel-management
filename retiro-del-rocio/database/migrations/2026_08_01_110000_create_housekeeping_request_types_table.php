<?php

use App\Models\HousekeepingRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('housekeeping_request_types', function (Blueprint $table) {
            $table->id();
            // The value stored on housekeeping_requests.type and sent by the
            // guest tablet — stable once created, so existing requests never
            // orphan even if the label is later reworded.
            $table->string('key')->unique();
            $table->string('label');
            // A Flutter/Material icon key (see the guest tablet's icon map) —
            // a curated set, not free text, so admin can never pick a name the
            // app doesn't know how to render.
            $table->string('icon')->default('cleaning_services');
            // The guest's Service Request screen only offers guest_visible
            // types — the reception-raised checkout inspection reuses this
            // same table but must never appear there.
            $table->boolean('guest_visible')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'guest_visible']);
        });

        // Seed with the exact set the app already shipped with, so this
        // migration is a pure refactor — nothing about the guest experience
        // changes until an admin actually adds something new.
        $now = now();
        DB::table('housekeeping_request_types')->insert([
            ['key' => 'towels', 'label' => 'Towels', 'icon' => 'dry_cleaning', 'guest_visible' => true, 'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'amenities', 'label' => 'Amenities', 'icon' => 'soap', 'guest_visible' => true, 'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'dnd', 'label' => 'Do Not Disturb', 'icon' => 'do_not_disturb_on', 'guest_visible' => true, 'is_active' => true, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'make_up_room', 'label' => 'Make Up Room', 'icon' => 'cleaning_services', 'guest_visible' => true, 'is_active' => true, 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'other', 'label' => 'Other', 'icon' => 'more_horiz', 'guest_visible' => true, 'is_active' => true, 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now],
            ['key' => HousekeepingRequest::CHECKOUT_INSPECTION, 'label' => 'Checkout Inspection', 'icon' => 'fact_check', 'guest_visible' => false, 'is_active' => true, 'sort_order' => 6, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('housekeeping_request_types');
    }
};
