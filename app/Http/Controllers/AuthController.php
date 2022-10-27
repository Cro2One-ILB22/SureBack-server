<?php

namespace App\Http\Controllers;

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

    public function getInstagramOTP()
    {
        if ($this->getRegisterValidator()->fails()) {
            return response()->json($this->getRegisterValidator()->errors(), 400);
        }

        $instagramService = new InstagramService();
        $instagramProfile = $instagramService->getProfileInfo(request()->username);

        if (!$instagramProfile) {
            return $this->getInstagramProfileError('Failed to get instagram profile');
        }

        $instagramId = $instagramProfile['id'];
        $validator = Validator::make(['instagram_id' => $instagramId], [
            'instagram_id' => 'unique:users',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $otpService = new OTPService();
        $reqData = ['instagram_id' => $instagramId];

        return response()->json($otpService->generateInstagramOTP($reqData));
    }

    public function verifyInstagramOTP()
    {
        if ($this->getRegisterValidator()->fails()) {
            return response()->json($this->getRegisterValidator()->errors(), 400);
        }

        $instagramService = new InstagramService();
        $instagramProfile = $instagramService->getProfileInfo(request()->all()['username']);

        if (!$instagramProfile) {
            return $this->getInstagramProfileError('Failed to get instagram profile');
        }

        $instagramId = $instagramProfile['id'];
        $validator = Validator::make(['instagram_id' => $instagramId], [
            'instagram_id' => 'unique:users',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
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
        return $this->register($instagramId);
    }

    private function register($instagramId)
    {
        $user = User::create(array_merge(
            request()->only(['name', 'email']),
            ['instagram_id' => $instagramId],
            ['password' => bcrypt(request()->password)],
        ));

        $user->roles()->attach(Role::where('slug', 'user')->first());
        $user->roles()->attach(Role::where('slug', request()->role)->first());

        if ($user) {
            if (request()->role === 'partner') {
                $user->partner()->create();
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
        return response()->json(auth()->user());
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

    private function getRegisterValidator()
    {
        return Validator::make(request()->all(), [
            'name' => 'required|string|between:2,100',
            'email' => 'required|string|email|max:100|unique:users',
            'password' => 'required|string|min:6',
            'username' => 'required|string',
            'role' => 'required|string|in:' . implode(',', config('enums.registerable_role')),
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
