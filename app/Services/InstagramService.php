<?php

namespace App\Services;

use App\Enums\VariableCategoryEnum;
use App\Enums\VariableEnum;
use App\Jobs\SaveFile;
use App\Models\CorporateInstagram;
use App\Models\User;
use App\Models\Variable;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Throwable;

class InstagramService
{
    static function callAPI($method, $path, $queries = [], $headers = [], $body = null, $auth = true, $username = null)
    {
        $variables = Variable::select('key', 'value')->whereHas('categories', function ($query) {
            $query->whereIn('slug', [VariableCategoryEnum::IG_WS]);
        })->get()->keyBy('key')->map(function ($item) {
            return $item->value;
        })->toArray();
        $variables = array_filter($variables, fn ($value) => $value !== null);

        $baseUrl = $variables[VariableEnum::IG_BASE_URL->value];
        $host = $variables[VariableEnum::IG_HOST->value];
        $xAppId = $variables[VariableEnum::IG_APP_ID->value];
        $userAgent = $variables[VariableEnum::IG_USER_AGENT->value];
        $referrer = $variables[VariableEnum::IG_REFERER->value];
        $origin = $variables[VariableEnum::IG_ORIGIN->value];
        $asbdId = $variables[VariableEnum::IG_ASBD_ID->value];
        $url = $baseUrl . $path;
        $requestedWith = $variables[VariableEnum::IG_REQUESTED_WITH->value];
        $altUsed = $variables[VariableEnum::IG_ALT_USED->value];
        $secFetchSite = $variables[VariableEnum::IG_SEC_FETCH_SITE->value];
        $secFetchMode = $variables[VariableEnum::IG_SEC_FETCH_MODE->value];
        $secFetchDest = $variables[VariableEnum::IG_SEC_FETCH_DEST->value];
        $te = $variables[VariableEnum::IG_TE->value];

        $generalHeaders = [
            'Host' => $host,
            'X-IG-App-ID' => $xAppId,
            'User-Agent' => $userAgent,
            'Referer' => $referrer,
            'Origin' => $origin,
            'X-ASBD-ID' => $asbdId,
            'X-Requested-With' => $requestedWith,
            'Alt-Used' => $altUsed,
            'Sec-Fetch-Site' => $secFetchSite,
            'Sec-Fetch-Mode' => $secFetchMode,
            'Sec-Fetch-Dest' => $secFetchDest,
            'TE' => $te,
        ];

        $response = Http::acceptJson();

        if ($auth) {
            if ($username) {
                $account = CorporateInstagram::where('username', $username)->first();
            } else {
                $account = CorporateInstagram::where('is_active', true)->orderBy('last_used_at', 'asc')->first();
            }
            if (!$account) {
                throw new BadRequestException('No active instagram account found');
            }
            if (!$account->is_active) {
                throw new BadRequestException('Try registering again');
            }

            $csrfToken = $account->csrf_token;
            $sessionId = $account->session_id;

            $generalHeaders = array_merge($generalHeaders, [
                'X-CSRFToken' => $csrfToken,
            ]);
            $cookies = [
                'csrftoken' => $csrfToken,
                'sessionid' => $sessionId,
            ];

            $cookieJar = CookieJar::fromArray($cookies, $host);

            $response = $response->withOptions([
                'cookies' => $cookieJar,
            ]);
        }

        $headers = array_merge($generalHeaders, $headers);

        $response = $response
            ->withHeaders($headers)
            ->$method($url, $queries, $body);

        // InstagramService::ssoUsers($headers, $cookies);

        if (!$response->successful()) {
            Log::error($response->body());
            if ($auth) {
                $account->is_active = false;
                $account->save();
                return InstagramService::callAPI($method, $path, $queries, $headers, $body, $auth, $username);
            }
            throw new BadRequestException('Under maintenance');
        }

        if ($auth) {
            if (!$response->json()) {
                $account->is_active = false;
            }
            $account->last_used_at = now();
            $account->used_count += 1;
            $account->save();
            if (!$response->json()) {
                return InstagramService::callAPI($method, $path, $queries, $headers, $body, $auth, $username);
            }
        }

        return $response->json();
    }

    function setUserInstagramProperty($profileInfo)
    {
        try {
            $user = User::where('instagram_id', $profileInfo['id'])->first();
            if ($user) {
                // check if nullable image name format is <id>_<time>
                $userPicture = $user->getRawOriginal('profile_picture') ?? '';
                $pictureTimestamp = explode('_', $userPicture);
                $pictureTimestamp = end($pictureTimestamp);

                // if picture age is more than 1 day, update it
                if (!$pictureTimestamp || !is_numeric($pictureTimestamp) || time() - $pictureTimestamp > 86400 || time() - $pictureTimestamp < 0) {
                    $time = time();
                    $pictureName = "{$user->instagram_id}_{$time}";
                    SaveFile::dispatch([
                        'url' => $profileInfo['profile_pic_url'],
                        'path' => "profile/{$pictureName}",
                        'type' => 'image',
                    ]);
                    $user->profile_picture = $pictureName;
                }
                $user->instagram_username = $profileInfo['username'];
                $user->save();
            }
        } catch (Throwable $e) {
            info($e);
        }
    }

    function getProfile($username)
    {
        $profileInfo =  $this->getProfileInfo($username);
        $this->setUserInstagramProperty($profileInfo);

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

        if (!$responseJson) {
            $responseJson = $this->callAPI('GET', $path, $queries);
        }

        return $responseJson['data']['user'];
    }

    function getUserInfo($id)
    {
        $path = 'users/' . $id . '/info/';
        $responseJson = $this->callAPI('GET', $path);
        $userInfo = $responseJson['user'];
        $id = $userInfo['pk'];
        $username = $userInfo['username'];

        $this->setUserInstagramProperty($userInfo);

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

    function getInbox($cursor, $instagramToDM, $pending = false)
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
        $responseJson = $this->callAPI('GET', $path, $queries, username: $instagramToDM);

        return $responseJson['inbox'];
    }

    function getOTPFrom($instagramId, $instagramToDM, $cursor = null, $pending = true, $expirationMinutes = 5)
    {
        $inbox = $this->getInbox($cursor, $instagramToDM, $pending);
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
                return $this->getOTPFrom($instagramId, $instagramToDM, $cursor, false);
            } else {
                return null;
            }
        }

        if (!$otp) {
            $cursor = array_key_exists('oldest_cursor', $inbox) ? $inbox['oldest_cursor'] : null;
            if ($cursor) {
                return $this->getOTPFrom($instagramId, $instagramToDM, $cursor, $pending);
            } else {
                if ($pending) {
                    return $this->getOTPFrom($instagramId, $instagramToDM, $cursor, false);
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

    function verifyOTP($instagramId, $instagramToDM)
    {
        $otp = $this->getOTPFrom($instagramId, $instagramToDM);
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

    private static function ssoUsers($headers, $cookies)
    {
        $url = 'https://www.instagram.com/api/v1/web/fxcal/ig_sso_users/';
        $headers = array_merge($headers, [
            'Accept' => '*/*',
            'Accept-Language' => 'en-US,en;q=0.5',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Connection' => 'keep-alive',
        ]);

        $response = Http::withHeaders($headers)->withCookies($cookies, 'www.instagram.com')->post($url);
        return $response->json();
    }
}
