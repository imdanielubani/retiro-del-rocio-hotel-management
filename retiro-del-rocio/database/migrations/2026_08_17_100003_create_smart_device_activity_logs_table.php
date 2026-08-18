<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Structural copy of `device_activity_logs`, scoped to `smart_device_id`.
 * Logs: assigned, unassigned, renamed, command_sent, command_failed,
 * scene_activated, synced. Never logs Tuya secrets — only device id, command
 * payload, and outcome.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smart_device_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('smart_device_id')->constrained('smart_devices')->cascadeOnDelete();
            $table->string('event');
            $table->string('description')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['smart_device_id', 'created_at']);
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_device_activity_logs');
    }
};
