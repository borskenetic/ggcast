<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\FineSetting;

class FineSettingSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run()
    {
        FineSetting::firstOrCreate(
            ['effective_from' => now()->toDateString()],
            [
                'fine_per_day' => 5.00,
                'max_fine' => 500.00,
                'grace_period_days' => 0,
                'loan_duration_days' => 7,
                'reserve_fine_per_day' => 5.00,
                'reserve_max_fine' => 500.00,
                'reserve_grace_period_days' => 0,
                'reserve_loan_duration_days' => 1,
                'effective_from' => now(),
            ]
        );
    }
}
