<?php

namespace App\Models;

use App\Enums\CoinTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserCoin extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'merchant_id',
        'coin_type',
    ];

    protected $casts = [
        'all_time_reward' => 'integer',
        'outstanding' => 'integer',
        'exchanged' => 'integer',
        'coin_type' => CoinTypeEnum::class,
    ];

    protected $hidden = [
        'customer_id',
        'merchant_id',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }
}
