<?php

namespace App\Models;

use App\Enums\CoinTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TokenCashback extends Model
{
    use HasFactory;

    protected $fillable = [
        'token_id',
        'amount',
        'percent',
        'coin_type',
    ];

    protected $hidden = [
        'id',
        'token_id',
    ];

    protected $casts = [
        'amount' => 'integer',
        'percent' => 'float',
        'coin_type' => CoinTypeEnum::class,
    ];

    public function token()
    {
        return $this->belongsTo(StoryToken::class, 'token_id');
    }
}
