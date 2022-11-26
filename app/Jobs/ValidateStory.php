<?php

namespace App\Jobs;

use App\Models\CustomerStory;
use App\Services\StoryService;
use Illuminate\Bus\Queueable;
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
        $story = CustomerStory::where('instagram_story_id', $story['instagram_story_id'])->first();
        $storyInspectedTimeBeforeExpiry = now()->addMinutes(3)->timestamp;

        if ($story->expiring_at > $storyInspectedTimeBeforeExpiry) {
            info('Story hasn\'t expired yet');
            return ValidateStory::dispatch(['instagram_story_id' => $story->instagram_story_id])
                ->delay(now()->addSeconds($story->expiring_at - $storyInspectedTimeBeforeExpiry));
        }

        $storyService = new StoryService();
        $storyService->validateStory($story);

        FinalizeStoryValidation::dispatch(['id' => $story->id]);
    }
}
