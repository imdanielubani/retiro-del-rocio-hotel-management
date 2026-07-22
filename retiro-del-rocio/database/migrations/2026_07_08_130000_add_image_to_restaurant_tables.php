<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guests pick a dining table / lounge space from a photo rather than a generic
 * icon. Stores a path (either a seeded "images/..." asset or an uploaded file
 * on the public disk), matching how Room, SpaService and Movie store images.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->string('image')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
};
