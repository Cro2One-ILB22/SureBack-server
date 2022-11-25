<?php

use App\Broadcasting\QRScanPurchaseChannel;
use App\Broadcasting\QRScanRequestChannel;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('qr-scan.request.{customerId}', QRScanRequestChannel::class);

Broadcast::channel('qr-scan.purchase.{merchantId}.{customerId}', QRScanPurchaseChannel::class);
