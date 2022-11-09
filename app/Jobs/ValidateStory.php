<?php

namespace App\Jobs;

use App\Enums\StoryApprovalStatusEnum;
use App\Enums\TransactionStatusEnum;
use App\Services\NotificationService;
use App\Services\StoryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ValidateStory implements ShouldQueue
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
        $story = $this->data;
        $storyService = new StoryService();
        $validated = $storyService->validateStory($story['instagram_story_id']);
        $notificationService = new NotificationService();
        $story = $validated['story'];
        $generalNotificationSubscription = $story->customer->notificationSubscriptions()->where('slug', 'general')->first();
        if ($validated['success']) {
            info('Story validated successfully');
            if ($story['approval_status'] === StoryApprovalStatusEnum::APPROVED && $story->cashback->transaction->status->slug !== TransactionStatusEnum::SUCCESS) {
                $storyService->sendCashback($story);
                $notificationService->sendAndSaveNotification(
                    'Cashback Approved',
                    'You have received a cashback of ' . $story->token->cashback->amount . ' for your purchase of ' . $story->token->purchase_amount . ' at ' . $story->token->merchant->name,
                    $generalNotificationSubscription,
                );
            }
        } else {
            info('Story validation failed');
            if ($story['approval_status'] === StoryApprovalStatusEnum::REJECTED) {
                $notificationService->sendAndSaveNotification(
                    'Cashback Rejected',
                    'Your cashback of ' . $story->token->cashback->amount . ' for your purchase of ' . $story->token->purchase_amount . ' at ' . $story->token->merchant->name . ' has been rejected',
                    $generalNotificationSubscription,
                );
            }
        }
    }
}
