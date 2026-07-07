<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movies', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('genre')->nullable();
            $table->string('duration')->nullable();      // "2h 30m"
            $table->string('rating')->nullable();         // "PG-13" / "8.5"
            $table->text('synopsis')->nullable();
            $table->string('poster_image')->nullable();
            $table->string('backdrop_image')->nullable();
            $table->string('trailer_url')->nullable();
            $table->unsignedInteger('adult_price')->default(5000);
            $table->unsignedInteger('child_price')->default(3000);
            $table->string('classification')->default('now_showing'); // now_showing | coming_soon
            $table->json('showtimes')->nullable();        // ["10:30 AM","1:00 PM"]
            $table->boolean('is_featured')->default(false); // hero banner
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('cinema_snacks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('price')->default(0);
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('cinema_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();            // ticket ID
            $table->string('reference')->nullable()->unique();
            $table->foreignId('movie_id')->nullable()->constrained()->nullOnDelete();
            $table->string('movie_title');
            $table->date('show_date');
            $table->string('show_time')->nullable();
            $table->json('seats')->nullable();           // ["B6","B7"]
            $table->unsignedSmallInteger('adult_tickets')->default(0);
            $table->unsignedSmallInteger('child_tickets')->default(0);
            $table->json('snacks')->nullable();          // [{name,qty,price}]
            $table->unsignedInteger('amount')->default(0);
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();
            $table->string('status')->default('confirmed');     // confirmed | used | cancelled
            $table->string('payment_status')->default('paid');  // paid | pending | refunded
            $table->string('payment_method')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cinema_bookings');
        Schema::dropIfExists('cinema_snacks');
        Schema::dropIfExists('movies');
    }
};
