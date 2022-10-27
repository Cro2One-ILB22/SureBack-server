<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InstagramController;
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
    Route::post('instagram-otp', [AuthController::class, 'getInstagramOTP']);
    Route::post('verify-instagram-otp', [AuthController::class, 'verifyInstagramOTP']);
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
    Route::post('token/generate', [InstagramController::class, 'generateToken'])->middleware('abilities:partner');
    Route::post('token/redeem', [InstagramController::class, 'redeemToken']);
});
