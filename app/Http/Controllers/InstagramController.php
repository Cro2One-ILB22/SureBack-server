<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
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
        $profile = $this->instagramService->getProfile($username);
        return response()->json($profile);
    }

    public function user($id)
    {
        $user = $this->instagramService->getUserInfo($id);
        return response()->json($user);
    }

    public function generateToken(GenerateTokenRequest $request)
    {
        $user = auth()->user();
        $validated = $request->validated();
        $purchaseAmount = $validated['purchase_amount'];
        return DB::transaction(function () use ($user, $purchaseAmount) {
            $transactionService = new TransactionService();
            $purchase = $transactionService->createPurchase($user, $purchaseAmount, $purchaseAmount);
            $token = $this->storyService->generateToken(auth()->user(), $purchase);
            return response()->json($token);
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

        $purchase = DB::transaction(function () use ($user, $customer, $purchaseAmount, $isRequestingForToken, $coinsUsed) {
            $coinsUsed = floor($coinsUsed / 1000) * 1000;
            $paymentAmount = $purchaseAmount - $coinsUsed;
            $transactionService = new TransactionService();
            $purchase = $transactionService->createPurchase($user, $purchaseAmount, $paymentAmount);
            $purchaseLoad = [];

            if ($coinsUsed > 0) {
                $transactionService->checkCoinsAvailability($customer, $user->id, $purchaseAmount, $coinsUsed);
                $transactionService->exchangeCoin($user, $customer, $coinsUsed, $purchase);
                $purchaseLoad += ['coinExchange' => fn ($query) => $query->amount()];
            }

            if ($isRequestingForToken) {
                $token = $this->storyService->generateToken($user, $purchase);
                $this->storyService->redeemToken($token->code, $customer);
                $purchaseLoad += ['token' => fn ($query) => $query->with('story', 'cashback')];
            }

            $purchase->load($purchaseLoad);

            return $purchase;
        });

        broadcast(new QRScanPurchaseEvent($user->id, $customer->id, purchase: $purchase))->toOthers();

        return response()->json($purchase);
    }

    public function redeemToken()
    {
        $validated = request()->validate([
            'token' => 'required|string',
        ]);
        $token = $validated['token'];
        $tokenResponse = $this->storyService->redeemToken($token, auth()->user());
        return response()->json($tokenResponse);
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

        $stories = $this->storyService->getMentioningStories($story->instagram_id, $story->token->instagram_id);
        return response()->json(['data' => $stories]);
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

        return response()->json($story);
    }

    public function approveStory(ApproveCustomerStoryRequest $request)
    {
        $validated = $request->validated();

        return $this->storyService->approveStory(auth()->user()->id, $validated);
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

        $userRoles = $user->roles->pluck('slug');
        $isMerchant = $userRoles->contains(RoleEnum::MERCHANT);

        if ($isMerchant) {
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

                if (!Schema::hasColumn('story_tokens', $column)) {
                    if ($column == 'last_status_update_at') {
                    } else {
                        return response()->json(['message' => 'Invalid order by'], Response::HTTP_BAD_REQUEST);
                    }
                }
                $tokens = $tokens->orderBy($column, $direction);
            }
        } else {
            $tokens = $tokens->orderBy('id', 'desc');
        }

        $userContradictiveRole = $isMerchant ? 'customer' : 'merchant';
        $tokens = $tokens
            ->with('story', 'cashback', "purchase.{$userContradictiveRole}")
            ->lastStatusUpdateAt()
            ->paginate();
        return response()->json($tokens);
    }

    function story()
    {
        $user = auth()->user();
        $userRoles = $user->roles->pluck('slug');
        $isMerchant = $userRoles->contains(RoleEnum::MERCHANT);
        $isCustomer = $userRoles->contains(RoleEnum::CUSTOMER);

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

        if ($isMerchant) {
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
            if ($isCustomer) {
                return response()->json(['message' => 'You are not allowed to see other customer\'s story'], Response::HTTP_UNAUTHORIZED);
            }
            $stories = $stories->where('customer_id', $request['customer_id']);
        }

        if (array_key_exists('merchant_id', $request)) {
            if ($isMerchant) {
                return response()->json(['message' => 'You are not allowed to see other merchants customers\' story'], Response::HTTP_UNAUTHORIZED);
            }
            $stories = $stories->whereHas('token', function ($token) use ($request) {
                $token->whereHas('purchase', function ($purchase) use ($request) {
                    $purchase->where('merchant_id', $request['merchant_id']);
                });
            });
        }

        if (array_key_exists('customer_name', $request)) {
            if ($isCustomer) {
                return response()->json(['message' => 'You are not allowed to see other customer\'s story'], Response::HTTP_UNAUTHORIZED);
            }
            $customerName = $request['customer_name'];
            $stories = $stories->whereHas('customer', function ($customer) use ($customerName) {
                $customer->whereRaw('LOWER(name) LIKE ?', ["%$customerName%"]);
            });
        }

        if (array_key_exists('merchant_name', $request)) {
            if ($isMerchant) {
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

        $userContradictiveRole = $isCustomer ? 'merchant' : 'customer';
        $userContradictiveRole = $userContradictiveRole == 'merchant' ? 'token.purchase.merchant' : 'customer';
        $stories = $stories
            ->with([$userContradictiveRole])
            ->with(['token' => function ($token) {
                $token->with(['cashback', 'story', 'purchase'])
                    ->lastStatusUpdateAt();
            }])
            ->orderBy('id', 'desc')
            ->paginate()
            ->through(function ($story) {
                $story->token->makeHidden('story');
                return $story;
            });
        return response()->json($stories);
    }
}
