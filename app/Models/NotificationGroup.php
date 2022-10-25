<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationGroup extends Model
{
    use HasFactory;

    public function subscriptions()
    {
        return $this->hasMany(NotificationSubscription::class);
    }
}
