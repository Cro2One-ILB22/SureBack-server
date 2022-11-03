<?php

namespace App\Services;

use App\Models\User;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class InstagramService
{
  private $keyHost = 'Host';
  private $keyXAppId = 'X-IG-App-ID';
  private $keySessionId = 'sessionid';
  private $keyUserAgent = 'User-Agent';
  private $keyReferer = 'Referer';
  private $keyOrigin = 'Origin';

  public function __construct()
  {
    $this->baseUrl = config('instagram.base_url');
    $this->host = config('instagram.host');
    $this->xAppId = config('instagram.x_app_id');
    $this->sessionId = config('instagram.session_id');
    $this->userAgent = config('instagram.user_agent');
    $this->referrer = config('instagram.referrer');
    $this->origin = config('instagram.origin');
  }

  function getProfile($username)
  {
    $profileInfo =  $this->getProfileInfo($username);
    return [
      'id' => $profileInfo['id'],
      'username' => $profileInfo['username'],
      'full_name' => $profileInfo['full_name'],
      'profile_pic_url' => $profileInfo['profile_pic_url'],
      'profile_pic_url_hd' => $profileInfo['profile_pic_url_hd'],
      'biography' => $profileInfo['biography'],
      'external_url' => $profileInfo['external_url'],
      'is_private' => $profileInfo['is_private'],
      'is_verified' => $profileInfo['is_verified'],
      'media_count' => $profileInfo['edge_owner_to_timeline_media']['count'],
      'follower_count' => $profileInfo['edge_followed_by']['count'],
      'following_count' => $profileInfo['edge_follow']['count'],
    ];
  }

  function getProfileInfo($username)
  {
    $path = 'users/web_profile_info/';
    $queries = [
      'username' => $username,
    ];
    $headers = [
      $this->keyHost => $this->host,
      $this->keyXAppId => $this->xAppId,
      $this->keyUserAgent => $this->userAgent,
      $this->keyReferer => $this->referrer,
      $this->keyOrigin => $this->origin,
    ];

    $url = $this->baseUrl . $path;

    $response = Http::acceptJson()
      ->withHeaders($headers)
      ->get($url, $queries);

    if (!$response->successful()) {
      return [
        'success' => false,
        'message' => 'Failed to get profile info',
        'status' => $response->status(),
      ];
    }
    return $response->json()['data']['user'];
  }

  function getUserInfo($id)
  {
    $path = 'users/' . $id . '/info/';
    $headers = [
      $this->keyHost => $this->host,
      $this->keyXAppId => $this->xAppId,
      $this->keyUserAgent => $this->userAgent,
      $this->keyReferer => $this->referrer,
      $this->keyOrigin => $this->origin,
    ];

    $cookies = [
      [
        $this->keySessionId => $this->sessionId,
      ],
      $this->host
    ];

    $url = $this->baseUrl . $path;

    $response = Http::acceptJson()
      ->withHeaders($headers)
      ->withCookies(...$cookies)
      ->get($url);

    if (!$response->successful()) {
      throw new \Exception('Failed to get user info');
    }
    $userInfo = $response->json()['user'];
    $id = $userInfo['pk'];
    $username = $userInfo['username'];

    User::where('instagram_id', $id)->update([
      'instagram_username' => $username,
    ]);

    return [
      'id' => $id,
      'username' => $username,
      'full_name' => $userInfo['full_name'] ?? null,
      'profile_pic_url' => $userInfo['profile_pic_url'] ?? null,
      'profile_pic_url_hd' => $userInfo['hd_profile_pic_url_info']['url'] ?? null,
      'biography' => $userInfo['biography'] ?? null,
      'external_url' => $userInfo['external_url'] ?? null,
      'is_private' => $userInfo['is_private'] ?? null,
      'is_verified' => $userInfo['is_verified'] ?? null,
      'media_count' => $userInfo['media_count'] ?? null,
      'follower_count' => $userInfo['follower_count'] ?? null,
      'following_count' => $userInfo['following_count'] ?? null,
    ];
  }

  function getInbox($cursor, $pending = false)
  {
    $path = 'direct_v2/' . ($pending ? 'pending_inbox' : 'inbox');
    $queries = [
      // 'visual_message_return_type' => 'unseen',
      // 'folder' => 'inbox',
      'thread_message_limit' => '1',
      'persistentBadging' => true,
      'cursor' => $cursor,
      // 'limit' => '20',
      // 'reason' => 'cold_start_fetch',
      // 'last_activity_at' => time(),
    ];
    $headers = [
      $this->keyHost => $this->host,
      $this->keyXAppId => $this->xAppId,
      $this->keyUserAgent => $this->userAgent,
      $this->keyReferer => $this->referrer,
      $this->keyOrigin => $this->origin,
    ];

    $cookieJar = CookieJar::fromArray([
      $this->keySessionId => $this->sessionId,
    ], $this->host);

    $url = $this->baseUrl . $path;

    $response = Http::acceptJson()
      ->withHeaders($headers)
      ->withOptions([
        'cookies' => $cookieJar,
      ])
      ->get($url, $queries);

    if (!$response->successful()) {
      throw new \Exception('Failed to get inbox');
    }
    return $response->json()['inbox'];
  }

  function getOTPFrom($instagramId, $cursor = null, $pending = true, $expirationMinutes = 5)
  {
    $inbox = $this->getInbox($cursor, $pending);
    $threads = $inbox['threads'];
    $otp = null;
    $expired = false;
    foreach ($threads as $thread) {
      $items = $thread['items'];
      foreach ($items as $item) {
        // check if not expired
        if (time() - substr($item['timestamp'], 0, 10) > $expirationMinutes * 60) {
          $expired = true;
          break;
        }

        if ($item['user_id'] == $instagramId && $item['item_type'] == 'text') {
          $otp = $item['text'];
          if ($otp) {
            return $otp;
          }
        }
      }
      if ($expired) {
        break;
      }
    }

    if ($expired) {
      if ($pending) {
        return $this->getOTPFrom($instagramId, $cursor, false);
      } else {
        return null;
      }
    }

    if (!$otp) {
      if (array_key_exists('oldest_cursor', $inbox)) {
        $cursor = $inbox['oldest_cursor'];
      } else {
        $cursor = null;
      }

      if ($cursor) {
        return $this->getOTPFrom($instagramId, $cursor, $pending);
      } else {
        if ($pending) {
          return $this->getOTPFrom($instagramId, $cursor, false);
        } else {
          return null;
        }
      }
    }

    return $otp;
  }

  public function getUniqueInstagramId($username)
  {
    $instagramProfile = $this->getProfileInfo($username);

    if (!$instagramProfile) {
      throw new \Exception('Failed to get instagram profile');
    }

    $instagramId = $instagramProfile['id'];
    $validator = Validator::make(['instagram_id' => $instagramId], [
      'instagram_id' => 'unique:users',
    ]);

    if ($validator->fails()) {
      throw new \Exception('Instagram ID already exists');
    }
    return $instagramId;
  }

  public function verifyOTP($username)
  {
    $instagramId = $this->getUniqueInstagramId($username);
    $otp = $this->getOTPFrom($instagramId);
    if (!$otp || !is_numeric($otp)) {
      throw new \Exception('Failed to get OTP');
    }

    $otpService = new OTPService();
    $reqData = [
      'otp' => $otp,
      'instagram_id' => $instagramId,
    ];

    if (!$otpService->verifyInstagramOTP($reqData)) {
      throw new \Exception('Invalid OTP');
    }
  }
}
