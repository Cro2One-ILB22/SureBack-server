<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
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
        $this->middleware('auth:sanctum', ['except' => ['login', 'register']]);
    }

    public function register()
    {
        $validator = Validator::make(request()->all(), [
            'name' => 'required|string|between:2,100',
            'email' => 'required|string|email|max:100|unique:users',
            'password' => 'required|string|min:6',
            'instagram_id' => 'required|int|unique:users',
            'role' => 'required|string|in:' . implode(',', config('enums.registerable_role')),
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $user = User::create(array_merge(
            request()->only(['name', 'email', 'instagram_id']),
            ['password' => bcrypt(request()->password)],
        ));

        $user->roles()->attach(Role::where('slug', 'user')->first());
        $user->roles()->attach(Role::where('slug', request()->role)->first());

        if ($user) {
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
}