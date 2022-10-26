<?php

namespace Database\Seeders;

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
        foreach (config('enums.otp_factor') as $slug) {
            OtpFactor::firstOrCreate([
                'slug' => $slug,
            ]);
        }
    }
}
