<?php

namespace App\Services;

use App\Enums\CashbackTypeEnum;
use App\Enums\InstagramStoryStatusEnum;
use App\Enums\PaymentInstrumentEnum;
use App\Enums\StoryApprovalStatusEnum;
use App\Enums\TransactionCategoryEnum;
use App\Enums\TransactionStatusEnum;
use App\Jobs\ValidateStory;
use App\Models\Cashback;
use App\Models\CustomerStory;
use App\Models\FinancialTransaction;
use App\Models\Ledger;
use App\Models\PaymentInstrument;
use App\Models\StoryToken;
use App\Models\TokenCashback;
use App\Models\TransactionCategory;
use App\Models\TransactionStatus;
use App\Models\User;
use App\Models\UserCoin;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class StoryService
{
  public function generateToken(User $user, int $purchaseAmount)
  {
    $todaysTokenCount = StoryToken::where('merchant_id', $user->id)
      ->where('created_at', '>=', now('Asia/Jakarta')->startOfDay())
      ->count();
    $dailyTokenLimit = $user->merchantDetail->daily_token_limit;

    if ($dailyTokenLimit && $todaysTokenCount >= $dailyTokenLimit) {
      throw new BadRequestException('Daily token limit reached');
    }

    if (!$user->merchantDetail->is_active_generating_token) {
      throw new BadRequestException('You don\'t activate token generation');
    }

    return DB::transaction(function () use ($user, $purchaseAmount) {
      $cashbackPercent = $user->merchantDetail->cashback_percent;
      $cashbackAmount = intval((($cashbackPercent ?? 0) / 100) * $purchaseAmount);
      // $cashbackLimit = $user->merchantDetail->cashback_limit;
      // if ($cashbackLimit) {
      //   $cashbackAmount = min($cashbackAmount, $cashbackLimit);
      // }
      // $balance_before = $user->balance;
      // $points_before = $user->points;
      // $userPayingPower = $this->payStory($user->balance, $user->points, $cashbackAmount);

      // if (!$userPayingPower) {
      //   throw new BadRequestException('Insufficient balance');
      // }

      // $balance_after = $userPayingPower['balance'];
      // $points_after = $userPayingPower['points'];

      // $transactionCategory = TransactionCategory::where('slug', 'story')->first();
      // $transactionStatus = TransactionStatus::where('slug', 'success')->first();
      // $transaction = new FinancialTransaction([
      //   'amount' => $cashbackAmount,
      //   'type' => 'D',
      // ]);
      // $transaction->user()->associate($user);
      // $transaction->category()->associate($transactionCategory);
      // $transaction->status()->associate($transactionStatus);
      // $transaction->save();

      // $ledger = new Ledger([
      //   'balance_before' => $balance_before,
      //   'balance_after' => $balance_after,
      //   'points_before' => $points_before,
      //   'points_after' => $points_after,
      // ]);

      // $ledger->transaction()->associate($transaction);
      // $ledger->save();

      // $user->balance = $balance_after;
      // $user->points = $points_after;
      // $user->save();

      // $corporateBalanceBefore = 0;
      // $corporateLedger = CorporateLedger::get()->last();

      // if ($corporateLedger) {
      //   $corporateBalanceBefore = $corporateLedger->balance_after;
      // }

      // $corporateLedger = new CorporateLedger([
      //   'amount' => $cashbackAmount,
      //   'type' => 'C',
      //   'balance_before' => $corporateBalanceBefore,
      //   'balance_after' => $corporateBalanceBefore + $cashbackAmount,
      // ]);
      // $corporateLedger->financialTransaction()->associate($transaction);
      // $corporateLedger->save();

      $instagramId = $user->instagram_id;
      $token = $this->generateUniqueToken($instagramId);
      $storyToken = new StoryToken([
        'token' => $token,
        'purchase_amount' => $purchaseAmount,
        'instagram_id' => $instagramId,
        'expires_at' => now()->addHours(18),
      ]);
      $storyToken->merchant()->associate($user);
      $storyToken->save();

      $tokenCashback = new TokenCashback([
        'amount' => $cashbackAmount,
        'percent' => $cashbackPercent,
        'type' => CashbackTypeEnum::LOCAL,
      ]);
      $tokenCashback->token()->associate($storyToken);
      $tokenCashback->save();
      // $storyToken->transactions()->attach($transaction);
      return [
        'token' => $token,
        'cashback_amount' => $cashbackAmount,
      ];
    });
  }

  public function redeemToken($token, User $customer)
  {
    $encryptedToken = CryptoService::encrypt($token);
    $storyToken = StoryToken::where('token', $encryptedToken)->first();
    if (!$storyToken) {
      throw new BadRequestException('Invalid token');
    }
    if ($storyToken->expires_at < now()) {
      throw new BadRequestException('Token expired');
    }
    if ($storyToken->story) {
      throw new BadRequestException('Token already redeemed');
    }
    $story = new CustomerStory([
      'instagram_id' => $customer->instagram_id,
    ]);
    $story->token()->associate($storyToken);
    $story->customer()->associate($customer);
    $story->save();

    $customerTransactionCategory = TransactionCategory::where('slug', TransactionCategoryEnum::CASHBACK)->first();
    $customerTransactionStatus = TransactionStatus::where('slug', TransactionStatusEnum::CREATED)->first();
    $customerTransaction = new FinancialTransaction([
      'amount' => $story->token->cashback->amount,
      'type' => 'C',
    ]);
    $customerTransaction->category()->associate($customerTransactionCategory);
    $customerTransaction->status()->associate($customerTransactionStatus);
    $customerTransaction->user()->associate($customer);
    $customerTransaction->save();

    $cashback = new Cashback();
    $cashback->story()->associate($story);
    $cashback->transaction()->associate($customerTransaction);
    $cashback->save();

    return $storyToken->load('merchant', 'story');
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
    return [
      'results' => [...array_map(function ($story) use ($storiesResponse, $submittedStories) {
        $submittedAt = in_array($story['pk'], array_column($submittedStories, 'instagram_story_id')) ? array_filter($submittedStories, function ($submittedStory) use ($story) {
          return $submittedStory['instagram_story_id'] == $story['pk'];
        })[0]['submitted_at'] : null;

        $id = $story['pk'];

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
      }, $mentionedStories)]
    ];
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
      'approval_status' => StoryApprovalStatusEnum::REVIEW,
      'instagram_story_status' => InstagramStoryStatusEnum::UPLOADED,
      'submitted_at' => now(),
    ]);

    ValidateStory::dispatch(['instagram_story_id' => $story->instagram_story_id])
      ->delay(now()->addSeconds($mentionedStory['expiring_at'] - now()->addMinutes(3)->timestamp));

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
      $query->where('merchant_id', $userId);
    })->first();
    if (!$story) {
      throw new BadRequestException('Story not found');
    }

    $story->approval_status = StoryApprovalStatusEnum::from($storyRequest['approved']);
    $story->note = $storyRequest['note'] ?? null;
    $story->save();

    if ($story->instagram_story_status === InstagramStoryStatusEnum::VALIDATED && !$story->cashback()->exists()) {
      $this->sendCashback($story);
      ValidateStory::dispatch(['instagram_story_id' => $story->instagram_story_id]);
    }

    return $story;
  }

  function getOnAirCustomerStory($instagramStoryId, $withExpiringAt = false)
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
      if ($withExpiringAt) {
        $story->expiring_at = $instagramStory['expiring_at'];
      }
      return $story;
    }
  }

  function validateStory($instagramStoryId)
  {
    $story = $this->getOnAirCustomerStory($instagramStoryId, true);
    info('validateStory', [$story]);
    if ($story) {
      if ($story->expiring_at < now()->addMinutes(3)->timestamp) {
        $story->instagram_story_status = InstagramStoryStatusEnum::VALIDATED;
        unset($story->expiring_at);
        $story->save();
      } else {
        ValidateStory::dispatch(['instagram_story_id' => $story->instagram_story_id])
          ->delay(now()->addSeconds($story->expiring_at - now()->addMinutes(3)->timestamp));
      }

      return [
        'success' => true,
        'story' => $story,
      ];
    }

    $story = CustomerStory::where('instagram_story_id', $instagramStoryId)->first();
    $story->update([
      'instagram_story_status' => InstagramStoryStatusEnum::DELETED,
    ]);

    return [
      'success' => false,
      'story' => $story,
    ];
  }

  function sendCashback($story)
  {
    $user = $story->customer;
    $merchant = $story->token->merchant;
    $cashbackAmount = $story->token->cashback->amount;
    DB::transaction(function () use ($merchant, $user, $story, $cashbackAmount) {
      $customerCoins = $user->coins;
      $customerCoinsAfter = $customerCoins + $cashbackAmount;

      $customerTransactionStatus = TransactionStatus::where('slug', TransactionStatusEnum::SUCCESS)->first();
      $paymentInstrument = PaymentInstrument::where('slug', PaymentInstrumentEnum::COINS)->first();

      $transaction = $story->cashback->transaction;
      $transaction->status()->associate($customerTransactionStatus);
      $transaction->save();

      $customerLedger = new Ledger([
        'before' => $customerCoins,
        'after' => $customerCoinsAfter,
      ]);
      $customerLedger->transaction()->associate($transaction);
      $customerLedger->instrument()->associate($paymentInstrument);
      $customerLedger->save();

      $user->coins = $customerCoinsAfter;
      $user->save();

      $merchant->merchantDetail->outstanding_coins += $cashbackAmount;
      $merchant->save();

      $userCoin = new UserCoin();
      $userCoin->customer()->associate($user);
      $userCoin->merchant()->associate($merchant);
      $userCoin->all_time += $cashbackAmount;
      $userCoin->outstanding += $cashbackAmount;
      $userCoin->save();
    });
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
    if (StoryToken::where('token', CryptoService::encrypt($token))->first()) {
      return $this->generateUniqueToken($instagramId);
    }
    return $token;
  }
}
