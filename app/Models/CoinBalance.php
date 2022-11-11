<?php

namespace App\Models;

use App\Enums\CoinTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoinBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'coin_type',
        'outstanding',
        'exchanged',
    ];

    protected $hidden = [
        'id',
        'user_id',
        'created_at',
    ];

    protected $casts = [
        'all_time_reward' => 'integer',
        'outstanding' => 'integer',
        'exchanged' => 'integer',
        'coin_type' => CoinTypeEnum::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
