<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Credentials now live in .env — drop the settings table.
        Schema::dropIfExists('ttlock_settings');

        Schema::table('bookings', function (Blueprint $table) {
            // Official TTLock QR code reference (QR-capable locks only).
            $table->string('qr_code_id')->nullable()->after('keyboard_pwd_id');
            $table->text('qr_code_link')->nullable()->after('qr_code_id');
        });

        // The self-generated QR image path is no longer used.
        if (Schema::hasColumn('bookings', 'qr_code_path')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('qr_code_path');
            });
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['qr_code_id', 'qr_code_link']);
            $table->string('qr_code_path')->nullable();
        });

        Schema::create('ttlock_settings', function (Blueprint $table) {
            $table->id();
            $table->string('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('checkin_time')->default('14:00');
            $table->string('checkout_time')->default('12:00');
            $table->timestamps();
        });
    }
};
