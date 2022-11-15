<?php

namespace App\Models;

use App\Enums\NotificationTopicEnum;
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

    protected $hidden = [
        'updated_at',
        'created_at',
    ];

    protected $casts = [
        'slug' => NotificationTopicEnum::class,
    ];

    public function subscriptions()
    {
        return $this->morphMany(NotificationSubscription::class, 'notification_subscriptionable');
    }
}
