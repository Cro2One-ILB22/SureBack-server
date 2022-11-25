<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QRScanRequestEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $merchant_id;
    private $customerId;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($merchantId, $customerId)
    {
        $this->merchant_id = $merchantId;
        $this->customerId = $customerId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('qr-scan.request.' . $this->customerId);
    }

    public function broadcastAs()
    {
        return 'qr-scan.request';
    }
}
