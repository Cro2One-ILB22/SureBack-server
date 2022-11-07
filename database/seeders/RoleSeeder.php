<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        foreach (RoleEnum::fullNames() as $slug => $name) {
            Role::firstOrCreate([
                'slug' => $slug,
            ], [
                'name' => $name,
            ]);
        }
    }
}
