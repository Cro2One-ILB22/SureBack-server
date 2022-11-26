<?php

namespace App\Jobs;

use App\Models\StoryToken;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExpireToken implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $data;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $storyToken = StoryToken::where('id', $this->data['id'])->first();
        $story = $storyToken->story;

        if ($story) {
            if (!$story->submitted_at) {
                $notificationService = new NotificationService();
                $generalNotificationSubscription = $story->customer->notificationSubscriptions()->where('slug', 'general')->first();

                $notificationService->sendAndSaveNotification(
                    'Token expired',
                    "Story for purchase of {$storyToken->purchase->purchase_amount} at {$storyToken->purchase->merchant->name} hasn't been submitted in time.",
                    $generalNotificationSubscription,
                );
            }
        }
    }
}
