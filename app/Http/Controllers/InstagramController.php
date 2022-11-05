<?php

namespace App\Http\Controllers;

use App\Models\CustomerStory;
use App\Models\User;
use App\Services\InstagramService;
use App\Services\StoryService;
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

    public function generateToken()
    {
        $purchaseAmount = request()->purchase_amount;
        return DB::transaction(function () use ($purchaseAmount) {
            try {
                $token = $this->storyService->generateToken(auth()->user(), $purchaseAmount);

                $customer_id = request()->customer_id;
                if ($customer_id) {
                    $customer = User::find($customer_id);
                    if (!$customer) {
                        throw new BadRequestException('Customer not found');
                    }
                    return $this->storyService->redeemToken($token['token'], $customer);
                }

                return response()->json($token);
            } catch (BadRequestException $e) {
                return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
            }
        });
    }

    public function redeemToken()
    {
        $token = request()->token;
        try {
            return $this->storyService->redeemToken($token, auth()->user());
        } catch (BadRequestException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    public function story()
    {
        $storyId = request()->validate([
            'story_id' => 'required',
        ])['story_id'];
        $story = CustomerStory::find($storyId);
        if (!$story) {
            return response()->json(['message' => 'Story not found'], Response::HTTP_NOT_FOUND);
        }

        $user = auth()->user();
        if ($user->id != $story->customer_id) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            return response()->json($this->storyService->getMentioningStories($story->instagram_id, $story->token->instagram_id));
        } catch (BadRequestException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
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
            return response()->json(['message' => 'Story not found'], Response::HTTP_NOT_FOUND);
        }

        $user = auth()->user();
        if ($user->id != $story->customer_id) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->storyService->submitStory($story, $request['instagram_story_id'])) {
            return response()->json(['message' => 'Failed to submit story'], Response::HTTP_BAD_REQUEST);
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
        } catch (BadRequestException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
