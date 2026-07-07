<?php

namespace Database\Seeders;

use App\Models\RestaurantTable;
use Illuminate\Database\Seeder;

class RestaurantTableSeeder extends Seeder
{
    public function run(): void
    {
        $tables = [
            // Dining tables
            ['name' => '2-Seater Table', 'area' => 'dining', 'capacity' => 2, 'shape' => 'round', 'description' => 'Intimate table for two.'],
            ['name' => '3-Seater Table', 'area' => 'dining', 'capacity' => 3, 'shape' => 'round', 'description' => 'Cosy round table.'],
            ['name' => '4-Seater Table', 'area' => 'dining', 'capacity' => 4, 'shape' => 'square', 'description' => 'Square table for small groups.'],
            ['name' => '6-Seater Table', 'area' => 'dining', 'capacity' => 6, 'shape' => 'rectangle', 'description' => 'Ideal for families.'],
            ['name' => '8-Seater Table', 'area' => 'dining', 'capacity' => 8, 'shape' => 'round', 'description' => 'Large round table for gatherings.'],

            // Lounge spaces
            ['name' => 'Lounge Booth', 'area' => 'lounge', 'capacity' => 4, 'shape' => 'round', 'description' => 'Relaxed booth for drinks.'],
            ['name' => 'Lounge Sofa', 'area' => 'lounge', 'capacity' => 6, 'shape' => 'rectangle', 'description' => 'Comfortable sofa seating.'],
            ['name' => 'VIP Lounge', 'area' => 'lounge', 'capacity' => 10, 'shape' => 'round', 'description' => 'Private VIP lounge area.'],
        ];

        foreach ($tables as $i => $t) {
            RestaurantTable::updateOrCreate(
                ['name' => $t['name']],
                array_merge($t, ['is_active' => true, 'sort_order' => $i]),
            );
        }
    }
}
