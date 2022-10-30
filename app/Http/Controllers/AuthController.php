<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\InstagramService;
use App\Services\OTPService;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum', ['except' => ['login', 'register', 'getInstagramOTP', 'verifyInstagramOTP']]);
    }

    public function getInstagramOTP(StoreUserRequest $request)
    {
        $validated = $request->safe();
        $instagramService = new InstagramService();
        $instagramProfile = $instagramService->getProfileInfo($validated->username);

        if (!$instagramProfile) {
            return $this->getInstagramProfileError('Failed to get instagram profile');
        }

        $instagramId = $instagramProfile['id'];
        $validator = Validator::make(['instagram_id' => $instagramId], [
            'instagram_id' => 'unique:users',
        ]);

        if ($validator->fails()) {
            return response()->json($validator, 400);
        }

        $otpService = new OTPService();
        $reqData = ['instagram_id' => $instagramId];

        return response()->json($otpService->generateInstagramOTP($reqData));
    }

    public function verifyInstagramOTP(StoreUserRequest $request)
    {
        $validated = $request->validated();
        $instagramService = new InstagramService();
        $instagramProfile = $instagramService->getProfileInfo($validated['username']);

        if (!$instagramProfile) {
            return $this->getInstagramProfileError('Failed to get instagram profile');
        }

        $instagramId = $instagramProfile['id'];
        $validator = Validator::make(['instagram_id' => $instagramId], [
            'instagram_id' => 'unique:users',
        ]);

        if ($validator->fails()) {
            return response()->json($validator, 400);
        }

        $otp = $instagramService->getOTPFrom($instagramId);
        if (!$otp || !is_numeric($otp)) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get OTP',
            ], 400);
        }

        $otpService = new OTPService();
        $reqData = [
            'otp' => $otp,
            'instagram_id' => $instagramId,
        ];

        if (!$otpService->verifyInstagramOTP($reqData)) {
            return response()->json(['message' => 'Invalid OTP'], 401);
        }
        return $this->register($request, $instagramId);
    }

    private function register(StoreUserRequest $request, $instagramId)
    {
        $validated = $request->safe()
            ->merge(['instagram_id' => $instagramId])
            ->except(['username', 'password']);
        $role = $validated['role'];
        $user = User::create(array_merge(
            $validated,
            ['password' => bcrypt($request->password)]
        ));

        $user->roles()->attach(Role::where('slug', 'user')->first());
        $user->roles()->attach(Role::where('slug', $role)->first());

        if ($user) {
            if ($role === 'partner') {
                $user->partnerDetail()->create();
            }
            return $this->respondWithToken($user);
        } else {
            return response()->json([
                'message' => 'User registration failed'
            ], 400);
        }
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login()
    {
        $credentials = request(['email', 'password']);

        if (!auth()->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $this->respondWithToken(auth()->user());
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        $user = auth()->user();
        if ($user->roles->contains('slug', 'partner')) {
            $user->partnerDetail->makeHidden(['user', 'user_id', 'created_at', 'updated_at']);
        }
        $roles = $user->roles->pluck('slug');
        unset($user->roles);
        $user->roles = $roles;
        return response()->json($user);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Successfully logged out']);
    }

    // /**
    //  * Refresh a token.
    //  *
    //  * @return \Illuminate\Http\JsonResponse
    //  */
    // public function refresh()
    // {
    //     return $this->respondWithToken(auth()->refresh());
    // }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken(User $user)
    {
        $roles = $user->roles->pluck('slug')->all();
        $expires_at = now()->addMinutes(config('sanctum.expiration'));
        $token = $user->createToken($user->id, $roles, $expires_at);

        return response()->json([
            'roles' => $roles,
            'access_token' => $token->plainTextToken,
            'token_type' => 'bearer',
            'expires_in' => $token->accessToken->expires_at->diffInSeconds(now()),
        ]);
    }

    private function getInstagramProfileError($message)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 400);
    }
}
