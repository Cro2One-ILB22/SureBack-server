<?php

namespace App\Http\Controllers;

use App\Services\InstagramService;
use App\Services\StoryService;

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
        return $this->storyService->generateToken(auth()->user(), $cashbackAmount);
    }
}
