<?php

namespace Database\Seeders;

use App\Enums\TransactionStatusEnum;
use App\Models\TransactionStatus;
use Illuminate\Database\Seeder;

class TransactionStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        foreach (TransactionStatusEnum::fullNames() as $slug => $name) {
            TransactionStatus::firstOrCreate([
                'slug' => $slug,
            ], [
                'name' => $name,
            ]);
        }
    }
}
