<?php

namespace App\Services;

use App\Models\CorporateTransaction;
use App\Models\CustomerStory;
use App\Models\FinancialTransaction;
use App\Models\StoryToken;
use App\Models\SuccessfulTransaction;
use App\Models\TransactionCategory;
use App\Models\TransactionStatus;
use App\Models\User;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class StoryService
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


  public function generateToken(User $user, $cashbackAmount)
  {
    return DB::transaction(function () use ($user, $cashbackAmount) {
      $balance_before = $user->balance;
      $points_before = $user->points;
      $userPayingPower = $this->payStory($user->balance, $user->points, $cashbackAmount);

      if (!$userPayingPower) {
        return null;
      }

      $balance_after = $userPayingPower['balance'];
      $points_after = $userPayingPower['points'];

      $transactionCategory = TransactionCategory::where('slug', 'story')->first();
      $transactionStatus = TransactionStatus::where('name', 'success')->first();
      $transaction = new FinancialTransaction([
        'amount' => $cashbackAmount,
        'type' => 'D',
      ]);
      $transaction->user()->associate($user);
      $transaction->category()->associate($transactionCategory);
      $transaction->status()->associate($transactionStatus);
      $transaction->save();

      $successfulTransaction = new SuccessfulTransaction([
        'balance_before' => $balance_before,
        'balance_after' => $balance_after,
        'points_before' => $points_before,
        'points_after' => $points_after,
      ]);

      $successfulTransaction->transaction()->associate($transaction);
      $successfulTransaction->save();

      $user->balance = $balance_after;
      $user->points = $points_after;
      $user->save();

      $corporateBalanceBefore = 0;
      $corporateTransaction = CorporateTransaction::get()->last();

      if ($corporateTransaction) {
        $corporateBalanceBefore = $corporateTransaction->balance_after;
      }

      $corporateTransaction = new CorporateTransaction([
        'amount' => $cashbackAmount,
        'type' => 'C',
        'balance_before' => $corporateBalanceBefore,
        'balance_after' => $corporateBalanceBefore + $cashbackAmount,
      ]);
      $corporateTransaction->financialTransaction()->associate($transaction);
      $corporateTransaction->save();

      $instagramId = $user->instagram_id;
      $token = hash("crc32", $instagramId . time());
      $storyToken = new StoryToken([
        'token' => $token,
        'instagram_id' => $instagramId,
        'expires_at' => now()->addHours(18),
      ]);
      $storyToken->partner()->associate($user);
      $storyToken->save();
      $storyToken->transactions()->attach($transaction);
      return [
        'token' => $token,
        'cashback_amount' => $cashbackAmount,
      ];
    });
  }

  public function redeemToken($token, User $customer)
  {
    $storyToken = StoryToken::where('token', $token)->first();
    if (!$storyToken) {
      return response()->json(['message' => 'Token not found'], 404);
    }
    if ($storyToken->expires_at < now()) {
      return response()->json(['message' => 'Token expired'], 400);
    }
    if ($storyToken->story) {
      return response()->json(['message' => 'Token already redeemed'], 400);
    }
    $story = new CustomerStory([
      'instagram_id' => $customer->instagram_id,
    ]);
    $story->token()->associate($storyToken);
    $story->customer()->associate($customer);
    $story->save();
    return $storyToken->load('partner', 'story');
  }

  private function payStory($balance, $points, $paymentAmount)
  {
    $points -= $paymentAmount;
    if ($points < 0) {
      $balance += $points;
      $points = 0;
    }
    if ($balance < 0) {
      return false;
    }
    return [
      'balance' => $balance,
      'points' => $points
    ];
  }

  public function getMentioningStories(int $uploader, int $mentioned)
  {
    $storiesResponse = $this->getStories($uploader, true);
    if (count($storiesResponse) === 0) {
      return [];
    }

    $mentionedStories = array_filter($storiesResponse['items'], function ($story) use ($mentioned) {
      $stickerContainerKey = 'story_bloks_stickers';
      $stickerKey = 'bloks_sticker';

      if (!array_key_exists($stickerContainerKey, $story)) {
        return false;
      }
      $mentionedStories = array_filter($story[$stickerContainerKey], function ($sticker) use ($stickerKey) {
        return $sticker[$stickerKey]['app_id'] === 'com.bloks.www.sticker.ig.mention.screen';
      });
      return array_filter($mentionedStories, function ($sticker) use ($mentioned, $stickerKey) {
        return $sticker[$stickerKey]['sticker_data']['ig_mention']['account_id'] == $mentioned;
      });
    });

    $submittedStories = $this->getLast24HoursSubmittedStoriesByMentionedUserId($uploader, $mentioned);
    return [
      'results' => [...array_map(function ($story) use ($storiesResponse, $submittedStories) {
        $submittedAt = in_array($story['pk'], array_column($submittedStories, 'instagram_story_id')) ? array_filter($submittedStories, function ($submittedStory) use ($story) {
          return $submittedStory['instagram_story_id'] === $story['pk'];
        })[0]['submitted_at'] : null;

        return [
          'id' => $story['pk'],
          'taken_at' => $story['taken_at'],
          'expiring_at' => $story['expiring_at'],
          'media_type' => $story['media_type'],
          'image_url' => $story['image_versions2']['candidates'][0]['url'],
          'video_url' => $story['media_type'] === 2 ? $story['video_versions'][0]['url'] : null,
          'music_metadata' => $story['music_metadata'],
          'story_url' => "https://www.instagram.com/stories/{$storiesResponse['user']['username']}/{$story['pk']}/",
          'submitted_at' => $submittedAt,
        ];
      }, $mentionedStories)]
    ];
  }

  function getStories($instagramId, bool $withUserInfo = false)
  {
    $path = 'feed/reels_media/';
    $queries = [
      'reel_ids' => $instagramId,
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
      return [];
    }
    $reels = $response->json()['reels_media'];
    if (count($reels) > 0) {
      if ($withUserInfo) {
        return $reels[0];
      }
      return $reels[0]['items'];
    }
    return [];
  }

  function submitStory(CustomerStory $customerStory, $instagramStoryId)
  {
    $mentionedStory = $this->getMentionedStory($customerStory, $instagramStoryId);
    if (!$mentionedStory) {
      return false;
    }

    $story = CustomerStory::where('id', $customerStory->id)
      ->update([
        'instagram_story_id' => $instagramStoryId,
        'image_uri' => $mentionedStory['image_versions2']['candidates'][0]['url'],
        'status' => strval(config('enums.story_status.review')),
        'submitted_at' => now(),
      ]);
    return $story;
  }

  function getLast24HoursSubmittedStories(int $userId)
  {
    return CustomerStory::where('instagram_id', $userId)
      ->where('submitted_at', '>=', now()->subDay())
      ->get()
      ->toArray();
  }

  function getLast24HoursSubmittedStoriesByMentionedUserId(int $mentionerUserId, int $mentionedUserId)
  {
    return CustomerStory::where('instagram_id', $mentionerUserId)
      ->where('submitted_at', '>=', now()->subDay())
      ->whereHas('token', function ($query) use ($mentionedUserId) {
        $query->where('instagram_id', $mentionedUserId);
      })
      ->get()
      ->toArray();
  }

  private function getMentionedStory(CustomerStory $customerStory, $instagramStoryId)
  {
    $stories = $this->getStories($customerStory->instagram_id);

    $mentionedStories = array_filter($stories, function ($story) use ($customerStory, $instagramStoryId) {
      return $story['id'] == $instagramStoryId . '_' . $customerStory->instagram_id;
    });

    if (count($mentionedStories) < 1) {
      return false;
    }

    return array_shift($mentionedStories);
  }
}
