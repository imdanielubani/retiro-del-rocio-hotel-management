<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('menu_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('department'); // food | drink — which admin menu (Kitchen / Bar & Lounge) owns it
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Seed the categories every menu item already used before this table
        // existed, so Kitchen/Bar & Lounge keep working immediately after
        // migrating — MenuItem.category is a plain string (not a foreign key,
        // to avoid a data migration on every existing row), and these rows
        // are what the admin "Manage Categories" list starts from.
        $now = now();
        DB::table('menu_categories')->insert([
            ['name' => 'Breakfast', 'slug' => 'breakfast', 'department' => 'food', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Starters', 'slug' => 'starters', 'department' => 'food', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Mains', 'slug' => 'mains', 'department' => 'food', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Specials', 'slug' => 'specials', 'department' => 'food', 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Desserts', 'slug' => 'desserts', 'department' => 'food', 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Snacks', 'slug' => 'snacks', 'department' => 'food', 'sort_order' => 6, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Drinks', 'slug' => 'drinks', 'department' => 'drink', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_categories');
    }
};
