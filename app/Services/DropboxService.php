<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class DropboxService
{
    public static function client()
    {
        $tokenProvider = new AutoRefreshingDropboxTokenService();
        $client = new \Spatie\Dropbox\Client($tokenProvider);
        return $client;
    }

    public function __construct()
    {
        $this->client = DropboxService::client();
    }

    function saveImage($path, $url)
    {
        $content = file_get_contents($url);
        $this->client->upload("images/{$path}.jpg", $content);
    }

    function saveVideo($path, $url)
    {
        $content = file_get_contents($url);
        $this->client->upload("videos/{$path}.mp4", $content);
    }

    function getTempImageLink($path)
    {
        try {
            return Cache::remember("dropbox.temp_link.image/{$path}", 10800, function () use ($path) {
                $link = $this->client->getTemporaryLink("images/{$path}.jpg");
                return $link;
            });
        } catch (\Exception $e) {
            return null;
        }
    }
}
