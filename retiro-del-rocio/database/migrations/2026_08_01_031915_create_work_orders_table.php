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
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            // A fault can be raised against a room, or against nothing in
            // particular (a hotel-wide asset) — hence nullable, and a free
            // `asset_label` for what's broken when there's no room to point at.
            $table->foreignId('room_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('asset_label')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('medium'); // low | medium | high | urgent
            $table->string('status')->default('new'); // new | accepted | in_progress | done
            $table->string('reported_by')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
