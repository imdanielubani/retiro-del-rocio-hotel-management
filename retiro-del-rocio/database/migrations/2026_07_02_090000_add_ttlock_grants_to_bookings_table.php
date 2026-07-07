<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Per-gate access records for multi-gate setups. One booking's shared
            // passcode is pushed to every configured gate lock; each entry holds
            // that gate's own keyboardPwdId + QR link + status.
            // Shape: [{lockId, name, keyboardPwdId, qrCodeId, qrLink, status, error}]
            $table->json('ttlock_grants')->nullable()->after('qr_code_link');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('ttlock_grants');
        });
    }
};
