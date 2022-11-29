<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AutoRefreshingDropboxTokenService implements \Spatie\Dropbox\TokenProvider
{
    private string $key;

    private string $secret;

    private string $refreshToken;

    public function __construct()
    {
        $this->key = env('DROPBOX_KEY');
        $this->secret = env('DROPBOX_SECRET');
        $this->refreshToken = env('DROPBOX_REFRESH_TOKEN');
    }

    public function getToken(): string
    {
        return Cache::remember('dropbox.access_token', 14300, function () {
            return $this->refreshToken();
        });
    }

    public function refreshToken(): string|bool
    {
        try {
            $res = Http::asForm()
                ->post("https://{$this->key}:{$this->secret}@api.dropbox.com/oauth2/token", [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $this->refreshToken,
                ]);

            if (!$res->successful()) {
                return false;
            }

            return trim(json_encode($res->json()['access_token']), '"');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return false;
        }
    }
}
