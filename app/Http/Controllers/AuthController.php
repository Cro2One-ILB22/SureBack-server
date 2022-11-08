<?php

namespace App\Http\Controllers;

use App\Enums\RegisterableRoleEnum;
use App\Enums\RoleEnum;
use App\Http\Requests\StoreUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\InstagramService;
use App\Services\OTPService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum', ['except' => ['login', 'register', 'instagramOTPRegister', 'register']]);
    }

    public function instagramOTPRegister(StoreUserRequest $request)
    {
        $validated = $request->safe();
        $instagramService = new InstagramService();
        try {
            $instagramId = $instagramService->getUniqueInstagramId($validated->username);
            $reqData = ['instagram_id' => $instagramId];

            $otpService = new OTPService();

            return response()->json($otpService->generateInstagramOTP($reqData));
        } catch (BadRequestException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    public function register(StoreUserRequest $request)
    {
        $validated = $request->validated();
        try {
            $username = $validated['username'];
            $instagramService = new InstagramService();
            $instagramId = $instagramService->getUniqueInstagramId($username);
            $instagramService->verifyOTP($username);
        } catch (BadRequestException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
        return $this->registerUser($request, $instagramId);
    }

    private function registerUser(StoreUserRequest $request, $instagramId)
    {
        $validated = $request->safe()
            ->merge([
                'instagram_id' => $instagramId,
                'instagram_username' => $request->safe()->username,
            ])
            ->except('username');
        $user = DB::transaction(function () use ($validated) {
            $role = RegisterableRoleEnum::from($validated['role']);
            $user = User::create($validated);

            $user->roles()->attach(Role::where('slug', RoleEnum::USER)->first());
            $user->roles()->attach(Role::where('slug', $role)->first());
            $this->instagramService->getProfile($user->instagram_username);

            if ($user) {
        if ($user) {
            if ($role === RegisterableRoleEnum::MERCHANT) {
                $user->merchantDetail()->create();
            }
            return $this->respondWithToken($user);
        } else {
                if ($role === RegisterableRoleEnum::MERCHANT) {
                    $user->merchantDetail()->create();
                }
                return $user;
            }
        });

        if (!$user) {
            return response()->json([
                'message' => 'User registration failed'
            ], Response::HTTP_BAD_REQUEST);
        }
        return $this->respondWithToken($user);
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
            return response()->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
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
        if ($user->roles->contains('slug', RoleEnum::MERCHANT)) {
            $user->merchantDetail->makeHidden(['user', 'user_id', 'created_at', 'updated_at']);
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
}
