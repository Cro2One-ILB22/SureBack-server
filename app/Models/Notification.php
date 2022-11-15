<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'body',
        'data',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'updated_at',
        'notification_subscription_id',
        'data',
        'image_id',
        'subscription',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
    ];

    protected $appends = [
        'category',
    ];

    public function getCategoryAttribute(): string
    {
        return $this->subscription->slug;
    }

    public function subscription()
    {
        return $this->belongsTo(NotificationSubscription::class, 'notification_subscription_id');
    }
}
