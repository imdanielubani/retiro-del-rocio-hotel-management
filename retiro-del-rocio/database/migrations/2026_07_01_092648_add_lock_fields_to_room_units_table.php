<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_units', function (Blueprint $table) {
            // Unique TTLock hardware lock id mapped to this physical door.
            $table->string('lock_id')->nullable()->after('status');
            $table->string('lock_alias')->nullable()->after('lock_id');
        });
    }

    public function down(): void
    {
        Schema::table('room_units', function (Blueprint $table) {
            $table->dropColumn(['lock_id', 'lock_alias']);
        });
    }
};
