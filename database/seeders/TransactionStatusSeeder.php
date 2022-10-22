<?php

namespace Database\Seeders;

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
        $statuses = [
            'created',
            'pending',
            'processing',
            'success',
            'failed',
            'cancelled',
            'refunded',
            'expired',
        ];
        foreach ($statuses as $status) {
            TransactionStatus::firstOrCreate([
                'name' => $status,
            ]);
        }
    }
}
