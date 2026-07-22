<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-device audit trail: login, logout, restart, sync, guest assigned/removed,
 * provisioning, room assignment, errors, etc. `user_id` is the admin actor when
 * the event was triggered from the dashboard; null when the device itself acted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('event');                 // e.g. login, sync, guest_assigned
            $table->string('description')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['device_id', 'created_at']);
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_activity_logs');
    }
};
