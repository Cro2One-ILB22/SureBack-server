<?php

namespace App\Jobs;

use App\Enums\InstagramStoryStatusEnum;
use App\Enums\StoryApprovalStatusEnum;
use App\Models\CustomerStory;
use App\Services\StoryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ApproveStory implements ShouldQueue
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
        $story = CustomerStory::where('id', $story['id'])->first();
        $storyId = $story->id;
        $merchantId = $story->token->purchase->merchant_id;
        $storyService = new StoryService();

        if ($story->approval_status == StoryApprovalStatusEnum::REVIEW) {
            if ($story->instagram_story_status == InstagramStoryStatusEnum::VALIDATED) {
                $storyService->approveStory(
                    $merchantId,
                    [
                        'id' => $storyId,
                        'approved' => 1,
                    ]
                );
            } else if ($story->instagram_story_status == InstagramStoryStatusEnum::DELETED) {
                $storyService->approveStory(
                    $merchantId,
                    [
                        'id' => $storyId,
                        'approved' => 0,
                        'note' => 'Story has been deleted',
                    ]
                );
            }
        }
    }
}
