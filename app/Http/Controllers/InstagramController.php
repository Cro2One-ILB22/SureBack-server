<?php

namespace App\Http\Controllers;

use App\Enums\StoryApprovalStatusEnum;
use App\Http\Requests\ApproveCustomerStoryRequest;
use App\Http\Requests\GenerateTokenRequest;
use App\Http\Requests\QRScanRequest;
use App\Models\CustomerStory;
use App\Models\StoryToken;
use App\Models\User;
use App\Services\InstagramService;
use App\Services\StoryService;
use App\Services\TransactionService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class InstagramController extends Controller
{
    public function __construct()
    {
        $this->instagramService = new InstagramService();
        $this->storyService = new StoryService();
    }
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke()
    {
        //
    }

    public function profile($username)
    {
        try {
            $profile = $this->instagramService->getProfile($username);
            return response()->json($profile);
        } catch (BadRequestException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    public function user($id)
    {
        try {
            $user = $this->instagramService->getUserInfo($id);
            return response()->json($user);
        } catch (BadRequestException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    public function generateToken(GenerateTokenRequest $request)
    {
        $user = auth()->user();
        $validated = $request->validated();
        $purchaseAmount = $validated['purchase_amount'];
        return DB::transaction(function () use ($user, $purchaseAmount) {
            try {
                $transactionService = new TransactionService();
                $purchase = $transactionService->createPurchase($user, $purchaseAmount, $purchaseAmount);
                $token = $this->storyService->generateToken(auth()->user(), $purchase);
                return response()->json($token);
            } catch (BadRequestException $e) {
                return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
            }
        });
    }

    public function qrScan(QRScanRequest $request)
    {
        $user = auth()->user();
        $validated = $request->validated();
        $purchaseAmount = $validated['purchase_amount'];
        $isRequestingForToken = $validated['is_requesting_for_token'] ?? false;
        $usedCoins = $validated['used_coins'] ?? 0;
        $paymentAmount = $purchaseAmount - $usedCoins;
        $customer = User::where('id', $validated['customer_id'])->first();

        try {
            $purchase = DB::transaction(function () use ($user, $customer, $purchaseAmount, $paymentAmount, $isRequestingForToken, $usedCoins) {
                $transactionService = new TransactionService();
                $purchase = $transactionService->createPurchase($user, $purchaseAmount, $paymentAmount);

                if ($usedCoins > 0) {
                    $transactionService->exchangeCoin($user, $customer, $usedCoins, $purchase);
                }

                if ($isRequestingForToken) {
                    $token = $this->storyService->generateToken($user, $purchase);
                    $this->storyService->redeemToken($token->code, $customer);
                    $purchase->load(['token' => fn ($query) => $query->with('story', 'cashback'), 'coinExchange']);
                }

                return $purchase;
            });

            return response()->json($purchase);
        } catch (BadRequestException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    public function redeemToken()
    {
        $validated = request()->validate([
            'token' => 'required|string',
        ]);
        $token = $validated['token'];
        try {
            $tokenResponse = $this->storyService->redeemToken($token, auth()->user());
            return response()->json($tokenResponse);
        } catch (BadRequestException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    public function myMentionedStories()
    {
        $storyId = request()->validate([
            'story_id' => 'required',
        ])['story_id'];
        $story = CustomerStory::where('id', $storyId)->first();
        if (!$story) {
            return response()->json(['message' => 'Story not found'], Response::HTTP_NOT_FOUND);
        }

        $user = auth()->user();
        if ($user->id != $story->customer_id) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $stories = $this->storyService->getMentioningStories($story->instagram_id, $story->token->instagram_id);
            return response()->json(['data' => $stories]);
        } catch (BadRequestException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    public function submitStory()
    {
        $request = request()->validate([
            'story_id' => 'required',
            'instagram_story_id' => 'required',
        ]);
        $storyId = $request['story_id'];
        $story = CustomerStory::where('id', $storyId)->first();
        if (!$story) {
            return response()->json(['message' => 'Story not found'], Response::HTTP_NOT_FOUND);
        }

        $user = auth()->user();
        if ($user->id != $story->customer_id) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $story = $this->storyService->submitStory($story, $request['instagram_story_id']);

        if (!$story) {
            return response()->json(['message' => 'Story not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json($story->load('token'));
    }

    public function approveStory(ApproveCustomerStoryRequest $request)
    {
        try {
            $validated = $request->validated();

            return $this->storyService->approveStory(auth()->user()->id, $validated);
        } catch (BadRequestException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    public function storyToken()
    {
        $user = auth()->user();
        $request = request()->validate([
            'expired' => 'boolean',
            'submitted' => 'boolean',
            'redeemed' => 'boolean',
        ]);

        if ($user->isMerchant()) {
            $tokens = StoryToken::whereHas('purchase', function ($purchase) use ($user) {
                $purchase->where('merchant_id', $user->id);
            });
        } else {
            $tokens = StoryToken::whereHas('story', function ($query) use ($user) {
                $query->where('customer_id', $user->id);
            })->with('story');
        }

        if (array_key_exists('expired', $request)) {
            $expired = $request['expired'];

            if ($expired == 0) {
                $tokens = $tokens->where('expires_at', '>', now());
            } else if ($expired == 1) {
                $tokens = $tokens->where('expires_at', '<=', now());
            }
        }

        if (array_key_exists('submitted', $request)) {
            $submitted = $request['submitted'];

            if ($submitted == 0) {
                $tokens = $tokens->whereHas('story', function ($query) {
                    $query->whereNull('submitted_at');
                });
            } else if ($submitted == 1) {
                $tokens = $tokens->whereHas('story', function ($query) {
                    $query->whereNotNull('submitted_at');
                });
            }
        }

        if (array_key_exists('redeemed', $request)) {
            $redeemed = $request['redeemed'];

            if ($redeemed == 0) {
                $tokens = $tokens->whereDoesntHave('story');
            } else if ($redeemed == 1) {
                $tokens = $tokens->whereHas('story');
            }
        }

        $tokens = $tokens
            ->with('story')
            ->orderBy('id', 'desc')
            ->paginate();
        return response()->json($tokens);
    }

    function story()
    {
        $user = auth()->user();
        $request = request()->validate([
            'customer' => 'integer',
            'expired' => 'boolean',
            'submitted' => 'boolean',
            'approved' => 'boolean',
        ]);

        if ($user->isMerchant()) {
            $stories = CustomerStory::whereHas('token', function ($token) use ($user) {
                $token->whereHas('purchase', function ($purchase) use ($user) {
                    $purchase->where('merchant_id', $user->id);
                });
            });

            if (array_key_exists('customer', $request)) {
                $stories = $stories->where('customer_id', $request['customer']);
            }
        } else {
            $customerId = $user->id;
            $stories = CustomerStory::where('customer_id', $customerId);
        }

        if (array_key_exists('expired', $request)) {
            $expired = $request['expired'];

            if ($expired == 0) {
                $stories = $stories->whereHas('token', function ($token) {
                    $token->where('expires_at', '>', now());
                });
            } else if ($expired == 1) {
                $stories = $stories->whereHas('token', function ($token) {
                    $token->where('expires_at', '<=', now());
                });
            }
        }

        if (array_key_exists('submitted', $request)) {
            $submitted = $request['submitted'];

            if ($submitted == 0) {
                $stories = $stories->whereNull('submitted_at');
            } else if ($submitted == 1) {
                $stories = $stories->whereNotNull('submitted_at');
            }
        }

        if (array_key_exists('approved', $request)) {
            $approved = $request['approved'];

            if ($approved == 0) {
                $stories = $stories->where('approval_status', StoryApprovalStatusEnum::REJECTED);
            } else if ($approved == 1) {
                $stories = $stories->where('approval_status', StoryApprovalStatusEnum::APPROVED);
            }
        }

        $stories = $stories
            ->with('token')
            ->orderBy('id', 'desc')
            ->paginate();
        return response()->json($stories);
    }
}
