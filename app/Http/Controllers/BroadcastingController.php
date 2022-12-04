<?php

namespace App\Http\Controllers;

use App\Events\QRScanPurchaseEvent;
use App\Events\QRScanRequestEvent;
use App\Http\Requests\QRScanPurchaseRequest;
use App\Http\Requests\QRScanResponseRequest;

class BroadcastingController extends Controller
{
    public function qrScanResponse(QRScanResponseRequest $request)
    {
        $userId = auth()->user()->id;
        $validated = $request->validated();
        $customerId = $validated['customer_id'];
        broadcast(new QRScanRequestEvent($userId, $customerId))->toOthers();

        return response()->json([
            'message' => 'QR Scan Response Sent',
        ]);
    }

    public function qrScanPurchase(QRScanPurchaseRequest $request)
    {
        $userId = auth()->user()->id;
        $validated = $request->validated();
        $merchantId = $validated['merchant_id'];
        $coinsUsed = (int) ($validated['coins_used'] ?? 0);
        $isRequestingForToken = (bool) ($validated['is_requesting_for_token'] ?? false);

        broadcast(new QRScanPurchaseEvent(
            $merchantId,
            $userId,
            [
                'coins_used' => $coinsUsed,
                'is_requesting_for_token' => $isRequestingForToken,
            ]
        ))->toOthers();

        return response()->json([
            'message' => 'Purchase Request Sent',
        ]);
    }
}
