<?php

namespace App\Http\Controllers;

use App\Enums\CoinTypeEnum;
use App\Enums\RegisterableRoleEnum;
use App\Enums\RoleEnum;
use App\Http\Requests\PreRegisterUserRequest;
use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\StoreUserRequest;
use App\Models\CorporateInstagram;
use App\Models\Role;
use App\Models\User;
use App\Services\DeviceService;
use App\Services\InstagramService;
use App\Services\NotificationService;
use App\Services\OTPService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

    public function instagramOTPRegister(PreRegisterUserRequest $request)
    {
        $validated = $request->safe();
        $instagramService = new InstagramService();
        try {
            $instagramId = $instagramService->getUniqueInstagramId($validated->username);
            $reqData = ['instagram_id' => $instagramId];

            $otpService = new OTPService();

            $account = CorporateInstagram::where('is_active', true)->orderBy('last_used_at', 'asc')->first();
            $otpResponse = $otpService->generateInstagramOTP($reqData);
            $otpResponse['instagram_to_dm'] = $account->username;

            return response()->json($otpResponse);
        } catch (BadRequestException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    public function register(StoreUserRequest $request)
    {
        $validated = $request->validated();
        try {
            $username = $validated['username'];
            $instagramToDM = $validated['instagram_to_dm'];
            $instagramService = new InstagramService();
            $instagramId = $instagramService->getUniqueInstagramId($username);
            $instagramService->verifyOTP($instagramId, $instagramToDM);
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
        return DB::transaction(function () use ($validated) {
            $role = RegisterableRoleEnum::from($validated['role']);
            $user = User::create($validated);

            $user->roles()->attach(Role::where('slug', RoleEnum::USER)->first());
            $user->roles()->attach(Role::where('slug', $role)->first());

            $user->coins()->createMany([
                ['coin_type' => CoinTypeEnum::LOCAL],
                ['coin_type' => CoinTypeEnum::GLOBAL],
            ]);

            $instagramService = new InstagramService();
            $instagramService->getProfile($user->instagram_username);

            if ($user) {
                (new NotificationService())->registerForNotification($user);
                if ($role === RegisterableRoleEnum::MERCHANT) {
                    $user->merchantDetail()->create([
                        'cashback_percent' => 3,
                    ]);
                }
            }

            if (!$user) {
                return response()->json([
                    'message' => 'User registration failed'
                ], Response::HTTP_BAD_REQUEST);
            }
            return $this->respondWithToken($user);
        });
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login()
    {
        $credentials = request(['email', 'password']);
        return DB::transaction(function () use ($credentials) {
            if (!auth()->attempt($credentials)) {
                return response()->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
            }
            $user = auth()->user();
            (new NotificationService())->registerForNotification($user);

            return $this->respondWithToken($user);
        });
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        $user = User::where('id', auth()->user()->id)
            ->with(['coins', 'roles'])
            ->withCoinsDebit('this_month')
            ->withCoinsDebit('this_week')
            ->withCoinsDebit('today')
            ->withCoinsCredit('this_month')
            ->withCoinsCredit('this_week')
            ->withCoinsCredit('today');
        $user = $user->first();
        $roles = $user->roles->pluck('slug');
        unset($user->roles);
        $user->roles = $roles;
        if ($user->roles->contains(RoleEnum::MERCHANT)) {
            $user->load([
                'merchantDetail' => fn ($query) => $query->with('addresses.location')
                ->todaysTokenCount()
            ]);
        }
        $user->makeHidden(['coins_debit_this_month', 'coins_debit_this_week', 'coins_debit_today', 'coins_credit_this_month', 'coins_credit_this_week', 'coins_credit_today']);
        $user->setAppends(['outstanding_coins', 'exchanged_coins']);
        $user->makeHidden('transactions');
        return response()->json($user);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        $user = auth()->user();
        $headers = $this->getDeviceHeaders();
        $rules = (new StoreDeviceRequest())->rules();
        $deviceIdentifierKey = 'identifier';
        $deviceIdRule = $rules[$deviceIdentifierKey];

        $validator = Validator::make($headers, [
            $deviceIdentifierKey => $deviceIdRule
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()], Response::HTTP_BAD_REQUEST);
        }

        $deviceService = new DeviceService();
        $deviceService->removeFromDevice($user, $validator->validated()['identifier']);

        $user->currentAccessToken()->delete();
        $deviceService = new DeviceService();
        $deviceService->removeFromDevice($user, $validator->validated());

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
        $headers = $this->getDeviceHeaders();
        $rules = (new StoreDeviceRequest())->rules();
        $validator = Validator::make($headers, $rules);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()], Response::HTTP_BAD_REQUEST);
        }

        $deviceService = new DeviceService();
        $deviceService->addDevice($user, $validator->validated());

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

    private function getDeviceHeaders()
    {
        $headers = getallheaders();
        return array_combine(array_map(function ($key) {
            return strtolower(str_replace(['x-device-', '-'], ['', '_'], strtolower($key)));
        }, array_keys($headers)), array_values($headers));
    }
}
