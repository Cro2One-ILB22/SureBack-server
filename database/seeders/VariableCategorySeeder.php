<?php

namespace Database\Seeders;

use App\Enums\VariableCategoryEnum;
use App\Models\VariableCategory;
use Illuminate\Database\Seeder;

class VariableCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        foreach (VariableCategoryEnum::fullNames() as $slug => $name) {
            VariableCategory::firstOrCreate([
                'slug' => $slug,
            ], [
                'name' => $name,
            ]);
        }
    }
}
