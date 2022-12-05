<?php

namespace App\Services;

use App\Enums\CashbackCalculationMethodEnum;
use App\Enums\CoinTypeEnum;
use App\Enums\InstagramStoryStatusEnum;
use App\Enums\StoryApprovalStatusEnum;
use App\Jobs\ApproveStory;
use App\Jobs\ExpireToken;
use App\Jobs\FinalizeStoryValidation;
use App\Jobs\SaveFile;
use App\Jobs\ValidateStory;
use App\Models\CustomerStory;
use App\Models\Purchase;
use App\Models\StoryToken;
use App\Models\TokenCashback;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        $cashbackPercent = $user->merchantDetail->cashback_percent;

        if ($dailyTokenLimit && $todaysTokenCount >= $dailyTokenLimit) {
            throw new BadRequestHttpException('Daily token limit reached');
        }

        if (!$user->merchantDetail->is_active_generating_token) {
            throw new BadRequestHttpException('You don\'t activate token generation');
        }

        if (!$cashbackPercent || $cashbackPercent <= 0) {
            throw new BadRequestHttpException('You haven\'t set cashback percent');
        }

        return DB::transaction(function () use ($user, $purchase, $cashbackPercent) {
            $cashbackCalculationMethod = $user->merchantDetail->cashback_calculation_method;
            $purchaseAmount = $purchase->purchase_amount;
            $paymentAmount = $purchase->payment_amount;
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

            $expiredAt = now()->addDay();

            $storyToken = new StoryToken([
                'code' => $tokenCode,
                'instagram_id' => $instagramId,
                'expires_at' => $expiredAt,
            ]);
            $storyToken->purchase()->associate($purchase);
            $storyToken->save();

            $cashback = new TokenCashback([
                'amount' => $cashbackAmount,
                'percent' => $cashbackPercent,
                'coin_type' => CoinTypeEnum::LOCAL,
                'cashback_calculation_method' => $cashbackCalculationMethod,
            ]);
            $cashback->token()->associate($storyToken);
            $cashback->save();

            ExpireToken::dispatch(['id' => $storyToken->id])->delay($expiredAt);
            return $storyToken;
        });
    }

    public function redeemToken(string $token, User $customer)
    {
        if ($customer->isMerchant()) {
            throw new BadRequestHttpException('Merchant can\'t redeem token');
        }
        $encryptedToken = CryptoService::encrypt($token);
        $storyToken = StoryToken::where('code', $encryptedToken)->first();
        if (!$storyToken) {
            throw new BadRequestHttpException('Invalid token');
        }
        if ($storyToken->expires_at < now()) {
            throw new BadRequestHttpException('Token expired');
        }
        if ($storyToken->story) {
            throw new BadRequestHttpException('Token already redeemed');
        }
        // $customerInstagram = (new InstagramService())->getProfile($customer->instagram_username);
        // if ($customerInstagram['follower_count'] < 100) {
        //     throw new BadRequestHttpException('Customer doesn\'t have enough followers');
        // }

        $transactionService = new TransactionService();
        $transactionService->initUserCoin($customer, $storyToken->purchase->merchant, CoinTypeEnum::LOCAL);
        $transactionService->initUserCoin($customer, $storyToken->purchase->merchant, CoinTypeEnum::GLOBAL);

        DB::transaction(function () use ($storyToken, $customer, $transactionService) {
            $story = new CustomerStory([
                'instagram_id' => $customer->instagram_id,
            ]);
            $story->token()->associate($storyToken);
            $story->customer()->associate($customer);
            $story->save();

            $transactionService->addStoryToToken($story, $storyToken);

            ApproveStory::dispatch(['id' => $story->id])->delay(now()->addDays(2));
        });

        return $storyToken->load('story', 'purchase');
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

        $responseJson = Cache::remember('ig.stories.' . $instagramId, 60, function () use ($path, $queries) {
            return InstagramService::callAPI('GET', $path, $queries);
        });

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

        $imageUri = $mentionedStory['image_versions2']['candidates'][0]['url'];
        $videoUri = $mentionedStory['media_type'] === 2 ? $mentionedStory['video_versions'][0]['url'] : null;
        $time = time();
        $imageName = "{$customerStory->id}_{$time}";
        $videoName = $videoUri ? $imageName : null;

        SaveFile::dispatch([
            'url' => $imageUri,
            'path' => "stories/{$imageName}",
            'type' => 'image',
        ]);
        if ($videoUri) {
            SaveFile::dispatch([
                'url' => $videoUri,
                'path' => "stories/{$videoName}",
                'type' => 'video',
            ]);
        }

        $customerStory->update([
            'instagram_story_id' => $instagramStoryId,
            'image_uri' => $imageName,
            'video_uri' => $videoName,
            'music_metadata' => $mentionedStory['music_metadata'] ?? null,
            'approval_status' => StoryApprovalStatusEnum::REVIEW,
            'instagram_story_status' => InstagramStoryStatusEnum::UPLOADED,
            'submitted_at' => now(),
            'assessed_at' => null,
            'expiring_at' => $mentionedStory['expiring_at'],
        ]);

        ValidateStory::dispatch(['instagram_story_id' => $customerStory->instagram_story_id])
            ->delay(now()->addSeconds($customerStory->expiring_at - $this->storyInspectedTimeBeforeExpiry));
        ApproveStory::dispatch(['id' => $customerStory->id])->delay(now()->addDay());

        $customerStory->image_uri = $imageUri;
        $customerStory->video_uri = $videoUri;
        return $customerStory;
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
            throw new NotFoundHttpException('Story not found');
        }

        $story->approval_status = StoryApprovalStatusEnum::from($storyRequest['approved']);
        $story->assessed_at = now();
        $story->note = $storyRequest['note'] ?? null;
        $story->save();

        FinalizeStoryValidation::dispatch(['id' => $story->id]);

        return $story;
    }

    function isStoryOnAir($customerStory)
    {
        $instagramId = $customerStory->instagram_id;
        $instagramStoryId = $customerStory->instagram_story_id;
        $stories = $this->getStories($instagramId);
        $stories = array_filter($stories, function ($story) use ($instagramStoryId) {
            return $story['pk'] == $instagramStoryId;
        });

        $instagramStory = array_shift($stories);

        if ($instagramStory) {
            return true;
        }
        return false;
    }

    function validateStory($customerStory)
    {
        info('validateStory', [$customerStory]);
        if ($this->isStoryOnAir($customerStory)) {
            $customerStory->instagram_story_status = InstagramStoryStatusEnum::VALIDATED;
            $customerStory->inspected_at = now();
            $customerStory->save();
        } else {
            $customerStory->update([
                'instagram_story_status' => InstagramStoryStatusEnum::DELETED,
                'inspected_at' => now(),
            ]);
        }
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
