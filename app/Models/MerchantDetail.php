<?php

namespace App\Models;

use App\Enums\CashbackCalculationMethodEnum;
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
        'cashback_calculation_method',
    ];

    protected $appends = [
        // 'last_token_generated_at',
        'cooldown_until',
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
        'cashback_calculation_method' => CashbackCalculationMethodEnum::class,
    ];

    protected $dates = [
        // 'last_token_generated_at',
        'last_token_generated_for_me_at',
        'cooldown_until',
    ];

    function scopeTodaysTokenCount($query)
    {
        return $query->addSelect([
            'todays_token_count' => function ($query) {
                $query->selectRaw('COUNT(*)')
                    ->from('story_tokens')
                    ->whereBetween('story_tokens.created_at', [
                        now('GMT+7')->startOfDay()->subHours(7),
                        now('GMT+7')->endOfDay()->subHours(7),
                    ])
                    ->whereIn('story_tokens.purchase_id', function ($query) {
                        $query->select('purchases.id')
                            ->from('purchases')
                            ->whereColumn('purchases.merchant_id', 'merchant_details.user_id');
                    });
            },
        ]);
    }

    // public function getLastTokenGeneratedAtAttribute()
    // {
    //     return $this->user
    //         ->purchasesAsMerchant()
    //         ->whereHas('token')
    //         ->latest('created_at')
    //         ->first()
    //         ->created_at
    //         ?? null;
    // }

    public function getCooldownUntilAttribute()
    {
        return $this->last_token_generated_for_me_at
            ? $this->last_token_generated_for_me_at->addDays(2)
            : null;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function addresses()
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    public function scopeWithLastTokenGeneratedForMeAt($query, $customerId)
    {
        return $query->addSelect([
            'last_token_generated_for_me_at' => Purchase::query()
                ->select('created_at')
                ->whereColumn('merchant_id', 'merchant_details.user_id')
                ->whereHas('token')
                ->where('customer_id', $customerId)
                ->latest('created_at')
                ->limit(1)
        ]);
    }
}
