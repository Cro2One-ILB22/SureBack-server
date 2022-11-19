<?php

namespace App\Services;

use App\Enums\CashbackCalculationMethodEnum;
use App\Enums\CoinTypeEnum;
use App\Enums\InstagramStoryStatusEnum;
use App\Enums\StoryApprovalStatusEnum;
use App\Jobs\ValidateStory;
use App\Models\CustomerStory;
use App\Models\Purchase;
use App\Models\StoryToken;
use App\Models\TokenCashback;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class StoryService
{
  public function __construct()
  {
    $this->storyInspectedTimeBeforeExpiry = now()->addMinutes(3)->timestamp;
  }
  public function generateToken(User $user, Purchase $purchase)
  {
    $todaysTokenCount = $user->merchantDetail->todaysTokenCount();
    $dailyTokenLimit = $user->merchantDetail->daily_token_limit;

    if ($dailyTokenLimit && $todaysTokenCount >= $dailyTokenLimit) {
      throw new BadRequestException('Daily token limit reached');
    }

    if (!$user->merchantDetail->is_active_generating_token) {
      throw new BadRequestException('You don\'t activate token generation');
    }

    return DB::transaction(function () use ($user, $purchase) {
      $cashbackCalculationMethod = $user->merchantDetail->cashback_calculation_method;
      $purchaseAmount = $purchase->purchase_amount;
      $paymentAmount = $purchase->payment_amount;
      $cashbackPercent = $user->merchantDetail->cashback_percent ?? 0;
      $cashbackPercentNormalized = $cashbackPercent / 100;
      $cashbackCalculatedWith = $purchaseAmount;
      $cashbackLimit = $user->merchantDetail->cashback_limit;
      if ($cashbackCalculationMethod === CashbackCalculationMethodEnum::PAYMENT_AMOUNT) {
        $cashbackCalculatedWith = $paymentAmount;
      }
      $cashbackAmount = intval($cashbackPercentNormalized * $cashbackCalculatedWith);
      if ($cashbackLimit) {
        $cashbackAmount = min($cashbackAmount, $cashbackLimit);
      }
      $instagramId = $user->instagram_id;

      $tokenCode = $this->generateUniqueToken($instagramId);

      $storyToken = new StoryToken([
        'code' => $tokenCode,
        'instagram_id' => $instagramId,
        'expires_at' => now()->addHours(18),
      ]);
      $storyToken->purchase()->associate($purchase);
      $storyToken->save();

      $tokenCashback = new TokenCashback([
        'amount' => $cashbackAmount,
        'percent' => $cashbackPercent,
        'coin_type' => CoinTypeEnum::LOCAL,
        'cashback_calculation_method' => $cashbackCalculationMethod,
      ]);
      $tokenCashback->token()->associate($storyToken);
      $tokenCashback->save();
      return $storyToken;
    });
  }

  public function redeemToken(string $token, User $customer)
  {
    if ($customer->isMerchant()) {
      throw new BadRequestException('Merchant can\'t redeem token');
    }
    $encryptedToken = CryptoService::encrypt($token);
    $storyToken = StoryToken::where('code', $encryptedToken)->first();
    if (!$storyToken) {
      throw new BadRequestException('Invalid token');
    }
    if ($storyToken->expires_at < now()) {
      throw new BadRequestException('Token expired');
    }
    if ($storyToken->story) {
      throw new BadRequestException('Token already redeemed');
    }

    $transactionService = new TransactionService();
    $transactionService->initUserCoin($customer, $storyToken->merchant, CoinTypeEnum::LOCAL);
    $transactionService->initUserCoin($customer, $storyToken->merchant, CoinTypeEnum::GLOBAL);

    DB::transaction(function () use ($storyToken, $customer, $transactionService) {
      $story = new CustomerStory([
        'instagram_id' => $customer->instagram_id,
      ]);
      $story->token()->associate($storyToken);
      $story->customer()->associate($customer);
      $story->save();

      $transactionService->addStoryToToken($story, $storyToken);
    });

    return $storyToken->load('story', 'purchase');
  }

  // private function payStory($balance, $points, $paymentAmount)
  // {
  //   $points -= $paymentAmount;
  //   if ($points < 0) {
  //     $balance += $points;
  //     $points = 0;
  //   }
  //   if ($balance < 0) {
  //     return false;
  //   }
  //   return [
  //     'balance' => $balance,
  //     'points' => $points
  //   ];
  // }

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
        return $sticker[$stickerKey]['app_id'] === 'com.bloks.www.sticker.ig.mention.screen' || $sticker[$stickerKey]['bloks_sticker_type'] === 'mention';
      });
      return array_filter($mentionedStories, function ($sticker) use ($mentioned, $stickerKey) {
        return $sticker[$stickerKey]['sticker_data']['ig_mention']['account_id'] == $mentioned;
      });
    });
    $submittedStories = $this->getLast24HoursSubmittedStoriesByMentionedUserId($uploader, $mentioned);

    return [...array_map(function ($story) use ($storiesResponse, $submittedStories) {
      $id = $story['pk'];
      $submittedInstagramStories = array_filter($submittedStories, fn ($submittedStory) => $submittedStory['instagram_story_id'] == $id);
      $submittedInstagramStory = reset($submittedInstagramStories);

      $submittedAt = null;
      if ($submittedInstagramStory && array_key_exists('submitted_at', $submittedInstagramStory)) {
        $submittedAt = $submittedInstagramStory['submitted_at'];
      }
      $submittedAt = in_array($id, array_column($submittedStories, 'instagram_story_id')) ? $submittedAt : null;

      return [
        'id' => is_numeric($id) ? (int) $id : $id,
        'taken_at' => $story['taken_at'],
        'expiring_at' => $story['expiring_at'],
        'media_type' => $story['media_type'],
        'image_url' => $story['image_versions2']['candidates'][0]['url'],
        'video_url' => $story['media_type'] === 2 ? $story['video_versions'][0]['url'] : null,
        'music_metadata' => $story['music_metadata'],
        'story_url' => "https://www.instagram.com/stories/{$storiesResponse['user']['username']}/{$story['pk']}/",
        'submitted_at' => $submittedAt,
      ];
    }, $mentionedStories)];
  }

  function getStories($instagramId, bool $withUserInfo = false)
  {
    $path = 'feed/reels_media/';
    $queries = [
      'reel_ids' => $instagramId,
    ];

    $responseJson = InstagramService::callAPI('GET', $path, $queries);

    $reels = $responseJson['reels_media'];
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

    $story = CustomerStory::where('id', $customerStory->id)->first();
    if (!$story) {
      return false;
    }
    $story->update([
      'instagram_story_id' => $instagramStoryId,
      'image_uri' => $mentionedStory['image_versions2']['candidates'][0]['url'],
      'video_uri' => $mentionedStory['media_type'] === 2 ? $mentionedStory['video_versions'][0]['url'] : null,
      'music_metadata' => $mentionedStory['music_metadata'] ?? null,
      'approval_status' => StoryApprovalStatusEnum::REVIEW,
      'instagram_story_status' => InstagramStoryStatusEnum::UPLOADED,
      'submitted_at' => now(),
      'assessed_at' => null,
      'expiring_at' => $mentionedStory['expiring_at'],
    ]);

    ValidateStory::dispatch(['instagram_story_id' => $story->instagram_story_id])
      ->delay(now()->addSeconds($story->expiring_at - $this->storyInspectedTimeBeforeExpiry));

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

  function approveStory($userId, $storyRequest)
  {
    $story = CustomerStory::where('id', $storyRequest['id'])->whereHas('token', function ($query) use ($userId) {
      $query->whereHas('purchase', function ($query) use ($userId) {
        $query->whereHas('merchant', function ($query) use ($userId) {
          $query->where('id', $userId);
        });
      });
    })->first();
    if (!$story) {
      throw new BadRequestException('Story not found');
    }

    $story->approval_status = StoryApprovalStatusEnum::from($storyRequest['approved']);
    $story->assessed_at = now();
    $story->note = $storyRequest['note'] ?? null;
    $story->save();

    if ($story->expiring_at < $this->storyInspectedTimeBeforeExpiry) {
      ValidateStory::dispatch(['instagram_story_id' => $story->instagram_story_id]);
    }

    return $story;
  }

  function getOnAirCustomerStory($instagramStoryId)
  {
    $story = CustomerStory::where('instagram_story_id', $instagramStoryId)->first();
    if (!$story) {
      return null;
    }

    $instagramId = $story->instagram_id;
    $stories = $this->getStories($instagramId);
    $stories = array_filter($stories, function ($story) use ($instagramStoryId) {
      return $story['pk'] == $instagramStoryId;
    });

    $instagramStory = array_shift($stories);

    if ($instagramStory) {
      return $story;
    }
  }

  function validateStory($instagramStoryId)
  {
    $story = $this->getOnAirCustomerStory($instagramStoryId);
    info('validateStory', [$story]);
    if ($story) {
      if ($story->expiring_at < $this->storyInspectedTimeBeforeExpiry) {
        $story->instagram_story_status = InstagramStoryStatusEnum::VALIDATED;
        $story->inspected_at = now();
        $story->save();
      } else {
        ValidateStory::dispatch(['instagram_story_id' => $story->instagram_story_id])
          ->delay(now()->addSeconds($story->expiring_at - $this->storyInspectedTimeBeforeExpiry));
      }

      return [
        'success' => true,
        'story' => $story,
      ];
    }

    $story = CustomerStory::where('instagram_story_id', $instagramStoryId)->first();
    $story->update([
      'instagram_story_status' => InstagramStoryStatusEnum::DELETED,
      'inspected_at' => now(),
    ]);

    return [
      'success' => false,
      'story' => $story,
    ];
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

  private function generateUniqueToken($instagramId)
  {
    $token = hash("crc32", $instagramId . time());
    if (StoryToken::where('code', CryptoService::encrypt($token))->first()) {
      return $this->generateUniqueToken($instagramId);
    }
    return $token;
  }
}
