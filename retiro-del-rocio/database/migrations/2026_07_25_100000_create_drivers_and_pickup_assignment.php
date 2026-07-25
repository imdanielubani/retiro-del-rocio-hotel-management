<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vehicle-pickup driver assignment.
 *
 * A `drivers` roster the reception desk and admin assign from, plus the columns
 * on `bookings` that record which driver was assigned to a guest's vehicle
 * pickup and where the pickup stands (unassigned → assigned → completed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('license_no')->nullable();
            $table->string('vehicle_details')->nullable(); // e.g. "Toyota Sienna · ABC-123-XY"
            $table->string('status')->default('available'); // available | off_duty
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('pickup_driver_id')->nullable()->after('pickup_flight_number')
                ->constrained('drivers')->nullOnDelete();
            $table->string('pickup_status')->default('unassigned')->after('pickup_driver_id'); // unassigned | assigned | completed
            $table->timestamp('pickup_assigned_at')->nullable()->after('pickup_status');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pickup_driver_id');
            $table->dropColumn(['pickup_status', 'pickup_assigned_at']);
        });

        Schema::dropIfExists('drivers');
    }
};
