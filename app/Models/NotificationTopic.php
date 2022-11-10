<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationTopic extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    public function subscriptions()
    {
        return $this->morphMany(NotificationSubscription::class, 'notification_subscriptionable');
    }
}
