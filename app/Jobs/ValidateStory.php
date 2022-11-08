<?php

namespace App\Jobs;

use App\Enums\StoryApprovalStatusEnum;
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
        if ($validated['success']) {
            $story = $validated['story'];
            if ($story['approval_status'] === StoryApprovalStatusEnum::APPROVED && !$story->cashback()->exists()) {
                $storyService->sendCashback($story);
                // send notification
            }
        } else {
            $story = $validated['story'];
            if ($story['approval_status'] === StoryApprovalStatusEnum::REJECTED) {
                // send notification
            }
        }
    }
}
