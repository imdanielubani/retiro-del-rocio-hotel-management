<?php

namespace Database\Seeders;

use App\Models\GymPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GymPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Basic Plan',
                'price' => 30000,
                'tagline' => 'Flexible membership options designed to match your fitness goals.',
                'is_featured' => false,
                'features' => [
                    'Unlimited access to gym floor',
                    'Basic workout guidance',
                    'Locker room & shower facilities',
                ],
            ],
            [
                'name' => 'Standard Plan',
                'price' => 70000,
                'tagline' => 'Flexible membership options designed to match your fitness goals.',
                'is_featured' => true,
                'features' => [
                    'Everything in Basic',
                    'Group fitness classes',
                    '1 personal training session / month',
                    'Body composition assessment',
                ],
            ],
            [
                'name' => 'Premium Plan',
                'price' => 150000,
                'tagline' => 'Flexible membership options designed to match your fitness goals.',
                'is_featured' => false,
                'features' => [
                    'Everything in Standard',
                    'Unlimited personal training',
                    'Sauna & recovery access',
                    'Personalised nutrition plan',
                    'Guest passes',
                ],
            ],
            [
                'name' => 'VIP Plan',
                'price' => 300000,
                'tagline' => 'The complete experience with priority access and dedicated support.',
                'is_featured' => false,
                'features' => [
                    'Everything in Premium',
                    '24/7 facility access',
                    'Dedicated personal trainer',
                    'Private locker & towel service',
                    'Monthly recovery massage',
                    'Priority class booking',
                ],
            ],
        ];

        foreach ($plans as $i => $p) {
            GymPlan::updateOrCreate(
                ['slug' => Str::slug($p['name'])],
                array_merge($p, ['is_active' => true, 'period' => 'monthly', 'sort_order' => $i]),
            );
        }
    }
}
