<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The guest's identity captured at reception check-in: which document was shown,
 * its number, and the uploaded scan stored in Cloudinary (public id + delivery
 * URL, so the admin booking page can display it). Plus a stable confirmation code
 * printed on the "Check-In Complete" screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('id_document_type')->nullable()->after('customer_phone');
            $table->string('id_document_number')->nullable()->after('id_document_type');
            $table->string('id_document_public_id')->nullable()->after('id_document_number');
            $table->string('id_document_url')->nullable()->after('id_document_public_id');
            $table->timestamp('identity_verified_at')->nullable()->after('id_document_url');
            $table->string('checkin_confirmation')->nullable()->after('checked_in_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'id_document_type',
                'id_document_number',
                'id_document_public_id',
                'id_document_url',
                'identity_verified_at',
                'checkin_confirmation',
            ]);
        });
    }
};
