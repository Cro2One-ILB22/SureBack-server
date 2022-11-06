<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantDetail extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'cashback_percent',
        'cashback_limit',
        'daily_token_limit',
        'is_active_generating_token',
    ];

    protected $appends = [
        'todays_token_count',
    ];

    protected $hidden = [
        'id',
        'user_id',
        'user',
        'created_at',
    ];
    
    protected $casts = [
        'cashback_percent' => 'float',
        'cashback_limit' => 'integer',
        'daily_token_limit' => 'integer',
        'is_active_generating_token' => 'boolean',
        'outstanding_coin' => 'integer',
        'exchanged_coin' => 'integer',
    ];

    public function todaysTokenCount(): Attribute
    {
        return new Attribute(
            fn () => $this->user->storyTokens
                ->where('created_at', '>=', now('Asia/Jakarta')->startOfDay())
                ->count()
        );
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}