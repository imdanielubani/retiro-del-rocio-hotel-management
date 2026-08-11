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
        Schema::table('staff_messages', function (Blueprint $table) {
            $table->dropIndex(['department', 'created_at']);
            $table->dropColumn('department');
        });

        Schema::table('staff_messages', function (Blueprint $table) {
            // The sorted pair of roles the channel is between, e.g.
            // "housekeeping_maintenance" or "admin_reception" — generalises
            // the old "department" (always paired with reception) so any two
            // of reception/housekeeping/maintenance/security/admin can share
            // a channel.
            $table->string('channel_key')->after('id');
            $table->index(['channel_key', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff_messages', function (Blueprint $table) {
            $table->dropIndex(['channel_key', 'created_at']);
            $table->dropColumn('channel_key');
        });

        Schema::table('staff_messages', function (Blueprint $table) {
            $table->string('department')->after('id');
            $table->index(['department', 'created_at']);
        });
    }
};
