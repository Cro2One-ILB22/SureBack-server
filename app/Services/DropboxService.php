<?php

namespace App\Services;

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
}
