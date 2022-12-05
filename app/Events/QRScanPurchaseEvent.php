<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class QRScanPurchaseEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    private $merchantId;
    private $customerId;
    public $purchase_request;
    public $purchase;
    public $total_purchase;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($merchantId, $customerId, $purchaseRequest = null, $purchase = null, $totalPurchase = null)
    {
        if ($purchase) {
            try {
                $purchase = $purchase->toArray();
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }
        }

        $this->merchantId = $merchantId;
        $this->customerId = $customerId;
        $this->purchase_request = $purchaseRequest;
        $this->purchase = $purchase;
        $this->total_purchase = $totalPurchase;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel("qr-scan.purchase.{$this->merchantId}.{$this->customerId}");
    }

    public function broadcastAs()
    {
        return 'qr-scan.purchase';
    }
}
