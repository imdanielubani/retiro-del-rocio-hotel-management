<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ttlock_settings', function (Blueprint $table) {
            $table->id();
            // TTLock OpenAPI application credentials.
            $table->string('client_id')->nullable();
            $table->text('client_secret')->nullable();
            // OAuth username/password of the TTLock account (needed for token grant).
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            // OAuth tokens (encrypted at rest via the model cast).
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            // Default guest access window used when a booking only stores dates.
            $table->string('checkin_time')->default('14:00');
            $table->string('checkout_time')->default('12:00');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ttlock_settings');
    }
};
