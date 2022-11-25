<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QRScanPurchaseEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    private $merchantId;
    private $customerId;
    public $purchase_request;
    public $purchase;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($merchantId, $customerId, $purchaseRequest = null, $purchase = null)
    {
        $this->merchantId = $merchantId;
        $this->customerId = $customerId;
        $this->purchase_request = $purchaseRequest;
        $this->purchase = $purchase;
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
