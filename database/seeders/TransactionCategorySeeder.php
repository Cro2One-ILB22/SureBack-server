<?php

namespace Database\Seeders;

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
        $categories = [
            'deposit' => 'Deposit',
            'cashback' => 'Cashback',
            'withdrawal' => 'Withdrawal',
            'story' => 'Story',
        ];
        foreach ($categories as $slug => $name) {
            TransactionCategory::firstOrCreate([
                'slug' => $slug,
                'name' => $name,
            ]);
        }
    }
}
