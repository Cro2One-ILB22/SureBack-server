<?php

namespace App\Http\Controllers;

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
    Route::get('profile', [InstagramController::class, 'profile']);
    Route::get('user/{id}', [InstagramController::class, 'user']);
    Route::post('token/generate', [InstagramController::class, 'generateToken'])->middleware('abilities:partner');
    Route::post('token/redeem', [InstagramController::class, 'redeemToken']);
    Route::get('story', [InstagramController::class, 'story']);
    Route::put('story', [InstagramController::class, 'updateStory']);
    Route::put('story/approval', [InstagramController::class, 'approveStory']);
});

Route::group([
    'middleware' => 'auth:sanctum',
    'prefix' => 'transaction'
], function ($router) {
    Route::get('', [TransactionController::class, 'index']);
});

Route::group([
    'middleware' => 'auth:sanctum',
    'prefix' => 'partner'
], function ($router) {
    Route::get('', [UserController::class, 'partner']);
});

Route::group([
    'middleware' => 'auth:sanctum',
    'prefix' => 'customer'
], function ($router) {
    Route::get('', [UserController::class, 'customer']);
});

Route::group([
    'middleware' => 'auth:sanctum',
    'prefix' => 'user'
], function ($router) {
    Route::put('', [UserController::class, 'update']);
    Route::put('partner-detail', [UserController::class, 'updatePartnerDetail'])->middleware('abilities:partner');
});
