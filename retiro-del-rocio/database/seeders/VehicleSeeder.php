<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class VehicleSeeder extends Seeder
{
    public function run(): void
    {
        // Matches the fleet previously hardcoded on the public airport pick-up popup.
        $vehicles = [
            ['name' => 'Standard Ride', 'type' => 'Sedan',  'price' => 37500,  'seats' => 4,  'suitcases' => 3,  'status' => 'available', 'image' => 'images/Standard Ride.png'],
            ['name' => 'Mini Bus',      'type' => 'Mini Bus','price' => 77500,  'seats' => 12, 'suitcases' => 6,  'status' => 'available', 'image' => 'images/Mini Bus.png'],
            ['name' => 'Bus',           'type' => 'Bus',     'price' => 150500, 'seats' => 14, 'suitcases' => 10, 'status' => 'available', 'image' => 'images/Bus.png'],
            ['name' => 'Executive SUV', 'type' => 'SUV',     'price' => 211500, 'seats' => 4,  'suitcases' => 3,  'status' => 'available', 'image' => 'images/Executive SUV.png'],
            ['name' => 'Luxury Ride',   'type' => 'Luxury',  'price' => 307500, 'seats' => 2,  'suitcases' => 3,  'status' => 'available', 'image' => 'images/Luxury Ride.png'],
        ];

        foreach ($vehicles as $i => $v) {
            Vehicle::updateOrCreate(
                ['slug' => Str::slug($v['name'])],
                array_merge($v, [
                    'free_cancellation' => true,
                    'sort_order' => $i,
                ]),
            );
        }
    }
}
