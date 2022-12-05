<?php

namespace App\Http\Controllers;

use App\Events\MyEvent;
use App\Events\QRScanRequestEvent;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group([
    'prefix' => 'auth'
], function ($router) {
    Route::post('register/instagram-otp', [AuthController::class, 'instagramOTPRegister']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login'])->name('login');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::get('me', [AuthController::class, 'me']);
});

Route::group([
    'middleware' => 'auth:sanctum',
    'prefix' => 'ig'
], function ($router) {
    Route::get('profile/{username}', [InstagramController::class, 'profile']);
    Route::get('user/{id}', [InstagramController::class, 'user']);
    Route::post('purchase/qr', [InstagramController::class, 'qrScan'])->middleware('abilities:merchant');
    Route::post('token/generate', [InstagramController::class, 'generateToken'])->middleware('abilities:merchant');
    Route::post('token/redeem', [InstagramController::class, 'redeemToken'])->middleware('abilities:customer');
    Route::get('token', [InstagramController::class, 'storyToken']);
    Route::get('story', [InstagramController::class, 'myMentionedStories'])->middleware('abilities:customer');
    Route::post('story/submit', [InstagramController::class, 'submitStory'])->middleware('abilities:customer');
    Route::put('story/approval', [InstagramController::class, 'approveStory'])->middleware('abilities:merchant');
});

Route::group([
    'middleware' => 'auth:sanctum',
    'prefix' => 'transaction'
], function ($router) {
    Route::get('', [TransactionController::class, 'index']);
});

Route::group([
    'middleware' => 'auth:sanctum',
    'prefix' => 'merchant'
], function ($router) {
    Route::get('', [UserController::class, 'merchants']);
    Route::get('{id}', [UserController::class, 'merchant']);
    Route::post('{id}/favorite', [UserController::class, 'favoriteMerchant']);
});

Route::group([
    'middleware' => 'auth:sanctum',
    'prefix' => 'customer'
], function ($router) {
    Route::get('', [UserController::class, 'customers']);
    Route::get('story', [InstagramController::class, 'story']);
    Route::get('{id}', [UserController::class, 'customer']);
});

Route::group([
    'middleware' => 'auth:sanctum',
    'prefix' => 'user'
], function ($router) {
    Route::put('', [UserController::class, 'update']);
    Route::put('merchant-detail', [UserController::class, 'updateMerchantDetail'])->middleware('abilities:merchant');
    Route::get('notification', [NotificationController::class, 'index']);
    Route::put('location', [UserController::class, 'updateLocation']);
    Route::put('merchant/location', [UserController::class, 'updateMerchantLocation'])->middleware('abilities:merchant');
});

Route::group([
    'middleware' => 'auth:sanctum',
    'prefix' => 'broadcasting'
], function ($router) {
    Route::post('qr/response', [BroadcastingController::class, 'qrScanResponse'])->middleware('abilities:merchant');
    Route::post('qr/purchase', [BroadcastingController::class, 'qrScanPurchase'])->middleware('abilities:customer');
    Route::post('qr/total-purchase', [BroadcastingController::class, 'qrScanTotalPurchase'])->middleware('abilities:merchant');
});

Route::group(
    [
        'middleware' => 'auth:sanctum',
        'prefix' => 'test'
    ],
    function ($router) {
        Route::get('', function () {
            // ValidateStory::dispatch([
            //     'instagram_story_id' => '2966942962107232846',
            // ]);

            // $pool = Pool::create();
            // $pool->add(function () {
            //     return Http::acceptJson()
            //         ->get('http://localhost:8001');
            // })->then(function ($output) {
            // })->catch(function (Throwable $exception) {
            //     // Handle exception
            // });

            return response()->json([
                'message' => 'Hello World',
            ]);
        });

        Route::get('event', function () {
            return response()->json(event(new MyEvent('Hello World')));
        });

        Route::post('event/qr-scan', function () {
            broadcast(new QRScanRequestEvent(auth()->user()->id, request()->customer_id));
            return response()->json([
                'message' => 'Success',
            ]);
        });
    }
);
