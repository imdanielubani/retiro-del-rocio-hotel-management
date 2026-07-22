<?php

use App\Enums\DeviceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every physical smart device in the hotel (tablets, smart TVs, and future
 * TTLock / IoT / Tuya / intercom units). `device_type_id` keeps the model
 * open to new categories without schema changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('device_uuid')->unique();
            $table->string('device_code')->unique();          // e.g. TAB-101
            $table->string('device_name');
            $table->foreignId('device_type_id')->constrained('device_types')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();

            // Hardware
            $table->string('serial_number')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('android_version')->nullable();
            $table->string('app_version')->nullable();

            // Network
            $table->string('mac_address')->nullable();
            $table->string('ip_address', 45)->nullable();      // fits IPv6

            // Telemetry (updated by heartbeat)
            $table->string('status')->default(DeviceStatus::Offline->value)->index();
            $table->unsignedTinyInteger('battery_level')->nullable();  // 0-100
            $table->unsignedTinyInteger('wifi_strength')->nullable();  // 0-100
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();

            // Provisioning
            $table->boolean('is_provisioned')->default(false)->index();
            $table->timestamp('provisioned_at')->nullable();
            $table->string('provision_token', 64)->nullable()->unique(); // encoded in the QR

            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
