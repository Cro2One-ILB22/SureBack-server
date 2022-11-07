<?php

namespace Database\Seeders;

use App\Enums\PaymentInstrumentEnum;
use App\Models\PaymentInstrument;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentInstrumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        foreach (PaymentInstrumentEnum::fullNames() as $slug => $name) {
            PaymentInstrument::firstOrCreate([
                'slug' => $slug,
            ], [
                'name' => $name,
            ]);
        }
    }
}
