<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function notification()
    {
        return $this->hasOne(UserNotification::class);
    }

    public function topic()
    {
        return $this->belongsTo(NotificationTopic::class);
    }

    public function group()
    {
        return $this->belongsTo(NotificationGroup::class);
    }

    public function scopeForUser($query, $user)
    {
        return $query->where('user_id', $user->id);
    }
}
