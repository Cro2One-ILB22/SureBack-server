<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'identifier',
        'name',
        'os',
        'os_version',
        'model',
        'notification_token',
    ];

    public function user()
    {
        return $this->belongsToMany(User::class, 'user_devices');
    }
}
