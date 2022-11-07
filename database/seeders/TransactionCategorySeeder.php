<?php

namespace Database\Seeders;

use App\Enums\TransactionCategoryEnum;
use App\Models\TransactionCategory;
use Illuminate\Database\Seeder;

class TransactionCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        foreach (TransactionCategoryEnum::fullNames() as $slug => $name) {
            TransactionCategory::firstOrCreate([
                'slug' => $slug,
            ], [
                'name' => $name,
            ]);
        }
    }
}
