<?php

namespace Database\Seeders;

use App\Enums\OTPFactorEnum;
use App\Models\OtpFactor;
use Illuminate\Database\Seeder;

class OtpFactorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        foreach (OTPFactorEnum::values() as $slug) {
            OtpFactor::firstOrCreate([
                'slug' => $slug,
            ]);
        }
    }
}
