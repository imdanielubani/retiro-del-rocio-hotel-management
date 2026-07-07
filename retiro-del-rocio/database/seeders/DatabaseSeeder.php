<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            RoomSeeder::class,
            VehicleSeeder::class,
            SpaServiceSeeder::class,
            GymPlanSeeder::class,
            RestaurantTableSeeder::class,
            MovieSeeder::class,
            CinemaSnackSeeder::class,
        ]);
    }
}
