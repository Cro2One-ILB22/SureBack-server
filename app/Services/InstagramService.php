<?php

namespace App\Services;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;

class InstagramService
{
  private $keyHost = 'Host';
  private $keyXAppId = 'X-IG-App-ID';
  private $keySessionId = 'sessionid';

  public function __construct()
  {
    $this->baseUrl = config('instagram.base_url');
    $this->host = config('instagram.host');
    $this->xAppId = config('instagram.x_app_id');
    $this->sessionId = config('instagram.session_id');
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
      return [
        'success' => false,
        'message' => 'Failed to get inbox',
        'status' => $response->status(),
      ];
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

    $oldestCursor = $inbox['oldest_cursor'];
    if (!$otp) {
      if ($oldestCursor) {
        return $this->getOTPFrom($instagramId, $oldestCursor, $pending);
      } else {
        if ($pending) {
          return $this->getOTPFrom($instagramId, $oldestCursor, false);
        } else {
          return null;
        }
      }
    }

    return $otp;
  }
}
