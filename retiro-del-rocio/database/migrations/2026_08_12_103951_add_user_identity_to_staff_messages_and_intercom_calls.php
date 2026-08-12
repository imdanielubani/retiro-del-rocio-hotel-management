<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff Chat and Staff Intercom move from department-wide channels
     * (any "bar" account sees the same "Bar" conversation, any ring to
     * "bar" wakes every bar tablet at once) to per-individual ones — a
     * channel/call is now addressed to one specific user, so multiple staff
     * under the same role are distinguishable and separately reachable.
     */
    public function up(): void
    {
        Schema::table('staff_messages', function (Blueprint $table) {
            $table->foreignId('sender_id')->nullable()->after('id')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('recipient_id')->nullable()->after('sender_id')
                ->constrained('users')->nullOnDelete();
            $table->index(['recipient_id', 'read_at']);
        });

        Schema::table('intercom_calls', function (Blueprint $table) {
            $table->foreignId('from_user_id')->nullable()->after('from_room_unit_id')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->after('to_room_unit_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('staff_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sender_id');
            $table->dropConstrainedForeignId('recipient_id');
        });

        Schema::table('intercom_calls', function (Blueprint $table) {
            $table->dropConstrainedForeignId('from_user_id');
            $table->dropConstrainedForeignId('to_user_id');
        });
    }
};
