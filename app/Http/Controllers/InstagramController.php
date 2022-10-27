<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\InstagramService;
use App\Services\StoryService;
use Illuminate\Support\Facades\DB;

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

    public function profile()
    {
        $username = request()->username;
        return $this->instagramService->getProfile($username);
    }

    public function generateToken()
    {
        $paymentAmount = request()->payment_amount;
        $cashbackAmount = (auth()->user()->partner->cashback_percent ?? 0) * $paymentAmount;
        return DB::transaction(function () use ($cashbackAmount) {
            $token = $this->storyService->generateToken(auth()->user(), $cashbackAmount);

            if (!$token) {
                return response()->json(['message' => 'Insufficient balance'], 400);
            }

            $customer_id = request()->customer_id;
            if ($customer_id) {
                $customer = User::find($customer_id);
                if ($customer) {
                    return $this->storyService->redeemToken($token['token'], $customer);
                }
            }

            return response()->json($token);
        });
    }

    public function redeemToken()
    {
        $token = request()->token;
        return $this->storyService->redeemToken($token, auth()->user());
    }
}
