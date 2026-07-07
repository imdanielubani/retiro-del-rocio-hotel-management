<?php

namespace Database\Seeders;

use App\Models\CinemaSnack;
use Illuminate\Database\Seeder;

class CinemaSnackSeeder extends Seeder
{
    public function run(): void
    {
        $snacks = [
            ['name' => 'Popcorn', 'price' => 2500, 'image' => 'images/popcorn.png'],
            ['name' => 'Hot Dog', 'price' => 3000, 'image' => 'images/hotdog.png'],
            ['name' => 'Burger', 'price' => 4000, 'image' => 'images/Burger.png'],
            ['name' => 'Chocolate Drink', 'price' => 2000, 'image' => 'images/chocolate drink.png'],
        ];

        foreach ($snacks as $i => $s) {
            CinemaSnack::updateOrCreate(
                ['name' => $s['name']],
                array_merge($s, ['is_active' => true, 'sort_order' => $i]),
            );
        }
    }
}
