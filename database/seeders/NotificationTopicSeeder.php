<?php

namespace Database\Seeders;

use App\Enums\NotificationTopicEnum;
use App\Models\NotificationTopic;
use Illuminate\Database\Seeder;

class NotificationTopicSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        foreach (NotificationTopicEnum::fullNames() as $slug => $name) {
            NotificationTopic::firstOrCreate([
                'slug' => $slug,
            ], [
                'name' => $name,
            ]);
        }
    }
}
