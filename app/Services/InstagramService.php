<?php

namespace App\Services;

use App\Models\CustomerStory;
use App\Models\User;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class InstagramService
{
  static function callAPI($method, $path, $queries = [], $headers = [], $body = null, $auth = true)
  {
    $keyHost = 'Host';
    $keyXAppId = 'X-IG-App-ID';
    $keySessionId = 'sessionid';
    $keyUserAgent = 'User-Agent';
    $keyReferer = 'Referer';
    $keyOrigin = 'Origin';

    $baseUrl = config('instagram.base_url');
    $host = config('instagram.host');
    $xAppId = config('instagram.x_app_id');
    $sessionId = config('instagram.session_id');
    $userAgent = config('instagram.user_agent');
    $referrer = config('instagram.referrer');
    $origin = config('instagram.origin');
    $url = $baseUrl . $path;

    $headers = array_merge($headers, [
      $keyHost => $host,
      $keyXAppId => $xAppId,
      $keyUserAgent => $userAgent,
      $keyReferer => $referrer,
      $keyOrigin => $origin,
    ]);

    $response = Http::acceptJson()
      ->withHeaders($headers);

    if ($auth) {
      $cookieJar = CookieJar::fromArray([
        $keySessionId => $sessionId,
      ], $host);

      $response = $response->withOptions([
        'cookies' => $cookieJar,
      ]);
    }

    $response = $response->$method($url, $queries, $body);

    if (!$response->successful()) {
      throw new BadRequestException('Failed to request API');
    }
    return $response->json();
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
    $responseJson = $this->callAPI('GET', $path, $queries, auth: false);

    return $responseJson['data']['user'];
  }

  function getUserInfo($id)
  {
    $path = 'users/' . $id . '/info/';
    $responseJson = $this->callAPI('GET', $path);
    $userInfo = $responseJson['user'];
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
    $responseJson = $this->callAPI('GET', $path, $queries);

    return $responseJson['inbox'];
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

  function getUniqueInstagramId($username)
  {
    $instagramProfile = $this->getProfileInfo($username);

    if (!$instagramProfile) {
      throw new BadRequestException('Instagram profile not found');
    }

    $instagramId = $instagramProfile['id'];
    $validator = Validator::make(['instagram_id' => $instagramId], [
      'instagram_id' => 'unique:users',
    ]);

    if ($validator->fails()) {
      throw new BadRequestException('Instagram already used');
    }
    return $instagramId;
  }

  function verifyOTP($username)
  {
    $instagramId = $this->getUniqueInstagramId($username);
    $otp = $this->getOTPFrom($instagramId);
    if (!$otp || !is_numeric($otp)) {
      throw new BadRequestException('OTP not found');
    }

    $otpService = new OTPService();
    $reqData = [
      'otp' => $otp,
      'instagram_id' => $instagramId,
    ];

    if (!$otpService->verifyInstagramOTP($reqData)) {
      throw new BadRequestException('Invalid OTP');
    }
  }

  function approveStory($userId, $customerStoryId, $approved)
  {
    $story = CustomerStory::where('id', $customerStoryId)->whereHas('token', function ($query) use ($userId) {
      $query->where('merchant_id', $userId);
    })->first();
    if (!$story) {
      throw new BadRequestException('Story not found');
    }

    $story->status = strval(config('enums.story_status')[array_search($approved, config('enums.story_status'))]);
    $story->save();

    return $story;
  }
}
