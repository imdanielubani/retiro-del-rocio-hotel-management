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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable(); // e.g. HVAC, Electrical, Plumbing, Kitchen Equipment
            // Optional room tie (a room's AC unit); free `location_label` covers
            // anything hotel-wide (a lobby generator, a lift) the same way
            // `WorkOrder::asset_label` does today.
            $table->foreignId('room_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('location_label')->nullable();
            $table->text('notes')->nullable();
            // Preventive-maintenance: null means no schedule is tracked for
            // this asset. When set, `isDueForService()` compares against
            // `last_serviced_at` (or `created_at` if never serviced).
            $table->unsignedInteger('service_interval_days')->nullable();
            $table->timestamp('last_serviced_at')->nullable();
            $table->timestamps();

            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
