<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-gate TTLock records for a visitor pass.
 *
 * The visitor gets ONE online code, but it is pushed to every configured gate
 * lock — each returning its own keyboardPwdId. We store all of them so the code
 * can be deleted from every gate the moment it is used or revoked.
 * Shape: [{lockId, keyboardPwdId}]
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitor_passes', function (Blueprint $table) {
            $table->json('ttlock_grants')->nullable()->after('lock_id');
        });
    }

    public function down(): void
    {
        Schema::table('visitor_passes', function (Blueprint $table) {
            $table->dropColumn('ttlock_grants');
        });
    }
};
