<?php

namespace App\Jobs;

use App\Services\DropboxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SaveFile implements ShouldQueue
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
        $url = $this->data['url'];
        $path = $this->data['path'];
        $type = $this->data['type'];
        $dropboxService = new DropboxService();
        if ($type == 'image') {
            $dropboxService->saveImage($path, $url);
        } else {
            $dropboxService->saveVideo($path, $url);
        }
    }
}
