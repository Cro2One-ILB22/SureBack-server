<?php

namespace App\Http\Controllers;

use App\Models\CustomerStory;
use App\Models\User;
use App\Services\InstagramService;
use App\Services\StoryService;
use Exception;
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

    public function user($id)
    {
        try {
            $user = $this->instagramService->getUserInfo($id);
            return response()->json($user);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function generateToken()
    {
        $purchaseAmount = request()->purchase_amount;
        return DB::transaction(function () use ($purchaseAmount) {
            try {
                $token = $this->storyService->generateToken(auth()->user(), $purchaseAmount);

                $customer_id = request()->customer_id;
                if ($customer_id) {
                    $customer = User::find($customer_id);
                    if ($customer) {
                        return $this->storyService->redeemToken($token['token'], $customer);
                    }
                }

                return response()->json($token);
            } catch (Exception $e) {
                return response()->json(['message' => $e->getMessage()], 400);
            }
        });
    }

    public function redeemToken()
    {
        $token = request()->token;
        try {
            return $this->storyService->redeemToken($token, auth()->user());
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function story()
    {
        $storyId = request()->validate([
            'story_id' => 'required',
        ])['story_id'];
        $story = CustomerStory::find($storyId);
        if (!$story) {
            return response()->json(['message' => 'Story not found'], 404);
        }

        $user = auth()->user();
        if ($user->id != $story->customer_id) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return response()->json($this->storyService->getMentioningStories($story->instagram_id, $story->token->instagram_id), 200);
    }

    public function updateStory()
    {
        $request = request()->validate([
            'story_id' => 'required',
            'instagram_story_id' => 'required',
        ]);
        $storyId = $request['story_id'];
        $story = CustomerStory::find($storyId);
        if (!$story) {
            return response()->json(['message' => 'Story not found'], 404);
        }

        $user = auth()->user();
        if ($user->id != $story->customer_id) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (!$this->storyService->submitStory($story, $request['instagram_story_id'])) {
            return response()->json(['message' => 'Failed to submit story'], 400);
        }

        return response()->json(['message' => 'Success']);
    }

    public function approveStory()
    {
        try {
            $request = request()->validate([
                'id' => 'required',
                'approved' => 'required|boolean',
            ]);
            $storyId = $request['id'];
            $approved = $request['approved'];

            return $this->instagramService->approveStory(auth()->user()->id, $storyId, $approved);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
