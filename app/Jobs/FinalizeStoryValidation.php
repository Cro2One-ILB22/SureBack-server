<?php

namespace App\Jobs;

use App\Enums\InstagramStoryStatusEnum;
use App\Enums\StoryApprovalStatusEnum;
use App\Enums\TransactionStatusEnum;
use App\Models\CustomerStory;
use App\Models\TransactionStatus;
use App\Services\NotificationService;
use App\Services\TransactionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FinalizeStoryValidation implements ShouldQueue
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
        $story = CustomerStory::where('id', $this->data['id'])->first();
        $approvalStatus = $story->approval_status;
        $instagramStoryStatus = $story->instagram_story_status;
        $cashbackStatus = $story->cashback->transaction->status->slug;

        $notificationService = new NotificationService();
        $generalNotificationSubscription = $story->customer->notificationSubscriptions()->where('slug', 'general')->first();


        if ($cashbackStatus !== TransactionStatusEnum::SUCCESS && $cashbackStatus !== TransactionStatusEnum::REJECTED) {
            if ($approvalStatus == StoryApprovalStatusEnum::APPROVED && $instagramStoryStatus == InstagramStoryStatusEnum::VALIDATED) {
                info('Story validated successfully');
                $transactionService = new TransactionService();
                $transactionService->sendCashback($story);

                $notificationService->sendAndSaveNotification(
                    'Cashback Approved',
                    'You have received a cashback of ' . $story->token->cashback->amount . ' for your purchase of ' . $story->token->purchase_amount . ' at ' . $story->token->purchase->merchant->name,
                    $generalNotificationSubscription,
                );
            } else if ($approvalStatus == StoryApprovalStatusEnum::REJECTED || $instagramStoryStatus == InstagramStoryStatusEnum::DELETED) {
                info('Story validation failed');
                $story->cashback->transaction->status()->associate(TransactionStatus::where('slug', TransactionStatusEnum::REJECTED)->first());
                $story->cashback->transaction->save();

                $notificationService->sendAndSaveNotification(
                    'Cashback Rejected',
                    'Your cashback of ' . $story->token->cashback->amount . ' for your purchase of ' . $story->token->purchase_amount . ' at ' . $story->token->purchase->merchant->name . ' has been rejected because ' . $story->note ?? 'the story was deleted.',
                    $generalNotificationSubscription,
                );
            }
        }
    }
}
