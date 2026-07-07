<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Temporary door passcode issued to the guest.
            $table->string('passcode')->nullable()->after('room_unit_id');
            // TTLock's id for the passcode (needed to update/delete it).
            $table->string('keyboard_pwd_id')->nullable()->after('passcode');
            // Stored QR image (relative to the public disk).
            $table->string('qr_code_path')->nullable()->after('keyboard_pwd_id');
            // pending | active | failed | deleted | disabled
            $table->string('ttlock_status')->nullable()->after('qr_code_path');
            // Last error message when access provisioning failed.
            $table->text('ttlock_error')->nullable()->after('ttlock_status');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'passcode', 'keyboard_pwd_id', 'qr_code_path', 'ttlock_status', 'ttlock_error',
            ]);
        });
    }
};
