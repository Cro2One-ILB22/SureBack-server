<?php

namespace Database\Seeders;

use App\Enums\VariableEnum;
use App\Models\Variable;
use App\Models\VariableCategory;
use Illuminate\Database\Seeder;

class VariableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $categories = VariableCategory::all();
        foreach (VariableEnum::dicts() as $key => $dict) {
            $values = [
                'name' => $dict['name'],
            ];

            if (isset($dict['value'])) {
                $values['value'] = $dict['value'];
            }

            if (isset($dict['description'])) {
                $values['description'] = $dict['description'];
            }

            $variable = Variable::updateOrCreate([
                'key' => $key,
            ], $values);

            if (isset($dict['categories'])) {
                $variable->categories()->sync($categories->whereIn('slug', $dict['categories'])->pluck('id'));
            }
        }
    }
}
