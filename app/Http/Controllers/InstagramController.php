<?php

namespace App\Http\Controllers;

use App\Enums\StoryApprovalStatusEnum;
use App\Events\QRScanPurchaseEvent;
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
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    public function user($id)
    {
        try {
            $user = $this->instagramService->getUserInfo($id);
            return response()->json($user);
        } catch (BadRequestException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
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
        $coinsUsed = $validated['coins_used'] ?? 0;
        $customer = User::where('id', $validated['customer_id'])->first();

        try {
            $purchase = DB::transaction(function () use ($user, $customer, $purchaseAmount, $isRequestingForToken, $coinsUsed) {
                $coinsUsed = floor($coinsUsed / 1000) * 1000;
                $paymentAmount = $purchaseAmount - $coinsUsed;
                $transactionService = new TransactionService();
                $purchase = $transactionService->createPurchase($user, $purchaseAmount, $paymentAmount);

                if ($coinsUsed > 0) {
                    $transactionService->checkCoinsAvailability($customer, $user->id, $purchaseAmount, $coinsUsed);
                    $transactionService->exchangeCoin($user, $customer, $coinsUsed, $purchase);
                }

                if ($isRequestingForToken) {
                    $token = $this->storyService->generateToken($user, $purchase);
                    $this->storyService->redeemToken($token->code, $customer);
                    $purchase->load(['token' => fn ($query) => $query->with('story', 'cashback'), 'coinExchange']);
                }

                return $purchase;
            });

            broadcast(new QRScanPurchaseEvent($user->id, $customer->id, purchase: $purchase))->toOthers();

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
            'order_by' => 'string',
            'order' => 'string',
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

        if (array_key_exists('order_by', $request)) {
            $orderBy = $request['order_by'];
            $orderBy = explode(',', $orderBy);

            foreach ($orderBy as $order) {
                $order = explode(':', $order);
                if (count($order) !== 2) {
                    return response()->json(['message' => 'Invalid order by'], Response::HTTP_BAD_REQUEST);
                }

                $column = $order[0];
                $direction = $order[1];
                if (!in_array($direction, ['asc', 'desc'])) {
                    return response()->json(['message' => 'Invalid order by'], Response::HTTP_BAD_REQUEST);
                }

                if (Schema::hasColumn('story_tokens', $column)) {
                    $tokens = $tokens->orderBy($column, $direction);
                } else {
                    return response()->json(['message' => 'Invalid order by'], Response::HTTP_BAD_REQUEST);
                }
            }
        } else {
            $tokens = $tokens->orderBy('id', 'desc');
        }

        $userContradictiveRole = $user->isCustomer() ? 'merchant' : 'customer';
        $tokens = $tokens
            ->with('story', 'cashback', "purchase.{$userContradictiveRole}")
            ->paginate();
        return response()->json($tokens);
    }

    function story()
    {
        $user = auth()->user();
        $request = request()->validate([
            'customer_id' => 'integer',
            'merchant_id' => 'integer',
            'customer_name' => 'string|nullable',
            'merchant_name' => 'string|nullable',
            'expired' => 'boolean',
            'submitted' => 'boolean',
            'approved' => 'boolean',
            'assessed' => 'boolean',
        ]);

        if ($user->isMerchant()) {
            $stories = CustomerStory::whereHas('token', function ($token) use ($user) {
                $token->whereHas('purchase', function ($purchase) use ($user) {
                    $purchase->where('merchant_id', $user->id);
                });
            });
        } else {
            $customerId = $user->id;
            $stories = CustomerStory::where('customer_id', $customerId);
        }

        if (array_key_exists('customer_id', $request)) {
            if ($user->isCustomer()) {
                return response()->json(['message' => 'You are not allowed to see other customer\'s story'], Response::HTTP_UNAUTHORIZED);
            }
            $stories = $stories->where('customer_id', $request['customer_id']);
        }

        if (array_key_exists('merchant_id', $request)) {
            if ($user->isMerchant()) {
                return response()->json(['message' => 'You are not allowed to see other merchants customers\' story'], Response::HTTP_UNAUTHORIZED);
            }
            $stories = $stories->whereHas('token', function ($token) use ($request) {
                $token->whereHas('purchase', function ($purchase) use ($request) {
                    $purchase->where('merchant_id', $request['merchant_id']);
                });
            });
        }

        if (array_key_exists('customer_name', $request)) {
            if ($user->isCustomer()) {
                return response()->json(['message' => 'You are not allowed to see other customer\'s story'], Response::HTTP_UNAUTHORIZED);
            }
            $customerName = $request['customer_name'];
            $stories = $stories->whereHas('customer', function ($customer) use ($customerName) {
                $customer->whereRaw('LOWER(name) LIKE ?', ["%$customerName%"]);
            });
        }

        if (array_key_exists('merchant_name', $request)) {
            if ($user->isMerchant()) {
                return response()->json(['message' => 'You are not allowed to see other merchants customers\' story'], Response::HTTP_UNAUTHORIZED);
            }
            $merchantName = $request['merchant_name'];
            $stories = $stories->whereHas('token', function ($token) use ($merchantName) {
                $token->whereHas('purchase', function ($purchase) use ($merchantName) {
                    $purchase->whereHas('merchant', function ($merchant) use ($merchantName) {
                        $merchant->whereRaw('LOWER(name) LIKE ?', ["%$merchantName%"]);
                    });
                });
            });
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

        if (array_key_exists('assessed', $request)) {
            $assessed = $request['assessed'];

            if ($assessed == 0) {
                $stories = $stories->whereNull('assessed_at');
            } else if ($assessed == 1) {
                $stories = $stories->whereNotNull('assessed_at');
            }
        }

        $userContradictiveRole = $user->isCustomer() ? 'merchant' : 'customer';
        $userContradictiveRole = $userContradictiveRole == 'merchant' ? 'token.purchase.merchant' : 'customer';
        $stories = $stories
            ->with(['token.cashback', 'token.purchase', $userContradictiveRole])
            ->orderBy('id', 'desc')
            ->paginate()
            ->through(function ($story) {
                $story->token->story = null;
                return $story;
            });
        return response()->json($stories);
    }
}
