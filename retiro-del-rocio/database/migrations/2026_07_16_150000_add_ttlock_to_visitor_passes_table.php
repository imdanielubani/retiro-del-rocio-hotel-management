<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two-channel visitor access.
 *
 * The existing `code` column becomes the OFFLINE code — a manual 6-digit code the
 * security officer keys in when the smart-lock/gateway is unreachable. Alongside
 * it we add the ONLINE code: a one-time TTLock passcode pushed to the gate lock,
 * which the visitor punches in themselves. Either admits the visitor exactly
 * once; using the online code at the lock is detected and auto-confirms the pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitor_passes', function (Blueprint $table) {
            // The one-time TTLock passcode the visitor enters at the gate lock.
            $table->string('online_code', 6)->nullable()->after('code')->index();

            // TTLock bookkeeping for the online code.
            $table->string('keyboard_pwd_id')->nullable()->after('online_code');
            $table->string('lock_id')->nullable()->after('keyboard_pwd_id');
            // active (code live on the lock) | offline (lock unreachable — offline
            // code only) | failed | used (spent) | deleted (revoked/cleaned up)
            $table->string('ttlock_status')->nullable()->after('lock_id');
            $table->text('ttlock_error')->nullable()->after('ttlock_status');

            // How the visitor was admitted: 'lock' (self-service TTLock) or
            // 'keypad' (officer keyed the offline code).
            $table->string('verified_via')->nullable()->after('verified_at');

            // When the online code stops working (visit window).
            $table->timestamp('expires_at')->nullable()->after('verified_via');
        });
    }

    public function down(): void
    {
        Schema::table('visitor_passes', function (Blueprint $table) {
            $table->dropColumn([
                'online_code', 'keyboard_pwd_id', 'lock_id',
                'ttlock_status', 'ttlock_error', 'verified_via', 'expires_at',
            ]);
        });
    }
};
