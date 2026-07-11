<?php

use App\Enums\DeviceMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Device allocation model:
 *  - Guest devices (guest tablets, smart TVs) bind to a specific room NUMBER
 *    (room_unit_id). room_id is kept as the denormalised suite for filtering.
 *  - Staff tablets bind to a single role instead of a room.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('mode')->default(DeviceMode::Guest->value)->after('device_type_id')->index();
            $table->foreignId('room_unit_id')->nullable()->after('room_id')->constrained('room_units')->nullOnDelete();
            $table->string('role')->nullable()->after('room_unit_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('room_unit_id');
            $table->dropColumn(['mode', 'role']);
        });
    }
};
