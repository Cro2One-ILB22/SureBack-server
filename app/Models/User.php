<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\AccountingEntryEnum;
use App\Enums\PaymentInstrumentEnum;
use App\Enums\RoleEnum;
use App\Enums\TransactionStatusEnum;
use App\Services\DropboxService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'profile_picture',
        'instagram_id',
        'instagram_username',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'story_tokens',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'balance' => 'integer',
        'coins' => 'integer',
        'is_favorite' => 'boolean',
    ];

    protected function password(): Attribute
    {
        return new Attribute(
            set: fn ($value) => bcrypt($value)
        );
    }

    protected function profilePicture(): Attribute
    {
        return new Attribute(
            fn ($value) => (new DropboxService())->getTempImageLink("profile/{$value}")
        );
    }

    /**
     * The roles that belong to the user.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function devices()
    {
        return $this->belongsToMany(Device::class, 'user_devices');
    }

    public function favoriteMerchantsAsCustomer()
    {
        return $this->belongsToMany(User::class, 'favorite_merchants', 'customer_id', 'merchant_id');
    }

    public function customersWhoFavoriteMe()
    {
        return $this->belongsToMany(User::class, 'favorite_merchants', 'merchant_id', 'customer_id');
    }

    public function notificationSubscriptions()
    {
        return $this->hasMany(NotificationSubscription::class);
    }

    public function bankAccounts()
    {
        return $this->hasMany(BankAccount::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function stories()
    {
        return $this->hasMany(CustomerStory::class, 'customer_id');
    }

    public function merchantDetail()
    {
        return $this->hasOne(MerchantDetail::class);
    }

    public function coins()
    {
        return $this->hasMany(CoinBalance::class);
    }

    public function customerCoins()
    {
        return $this->hasMany(UserCoin::class, 'customer_id');
    }

    public function merchantCoins()
    {
        return $this->hasMany(UserCoin::class, 'merchant_id');
    }

    public function purchasesAsCustomer()
    {
        return $this->hasMany(Purchase::class, 'customer_id');
    }

    public function purchasesAsMerchant()
    {
        return $this->hasMany(Purchase::class, 'merchant_id');
    }

    public function isMerchant()
    {
        return $this->roles()->where('slug', 'merchant')->exists();
    }

    public function isCustomer()
    {
        return $this->roles()->where('slug', 'customer')->exists();
    }

    public function addresses()
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    public function scopeWithIsFavoriteMerchant($query, $customerId)
    {
        return $query->withCount([
            'customersWhoFavoriteMe AS is_favorite' => function ($query) use ($customerId) {
                $query->where('customer_id', $customerId);
            },
        ]);
    }

    /**
     * @return array<string, int>
     */
    public function getOutstandingCoinsAttribute(): array
    {
        $thisMonth = $this->coins_credit_this_month - $this->coins_debit_this_month;
        $thisWeek = $this->coins_credit_this_week - $this->coins_debit_this_week;
        $today = $this->coins_credit_today - $this->coins_debit_today;

        if ($this->roles->contains(RoleEnum::MERCHANT)) {
            $thisMonth = $this->coins_debit_this_month - $this->coins_credit_this_month;
            $thisWeek = $this->coins_debit_this_week - $this->coins_credit_this_week;
            $today = $this->coins_debit_today - $this->coins_credit_today;
        }

        return [
            'this_month' => (int) $thisMonth,
            'this_week' => (int) $thisWeek,
            'today' => (int) $today,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function getExchangedCoinsAttribute(): array
    {
        $thisMonth = $this->coins_debit_this_month;
        $thisWeek = $this->coins_debit_this_week;
        $today = $this->coins_debit_today;

        if ($this->roles->contains(RoleEnum::MERCHANT)) {
            $thisMonth = $this->coins_credit_this_month;
            $thisWeek = $this->coins_credit_this_week;
            $today = $this->coins_credit_today;
        }

        return [
            'this_month' => (int) $thisMonth,
            'this_week' => (int) $thisWeek,
            'today' => (int) $today,
        ];
    }

    function scopeWithCoinsDebit($query, $period)
    {
        $coinsPaymentInstrumentId = PaymentInstrument::where('slug', PaymentInstrumentEnum::COINS)->first()->id;
        $successTransactionStatusId = TransactionStatus::where('slug', TransactionStatusEnum::SUCCESS)->first()->id;

        return $query->withCount([
            "transactions AS coins_debit_$period" => function ($query) use ($period, $coinsPaymentInstrumentId, $successTransactionStatusId) {
                $query->select(DB::raw('COALESCE(SUM(amount), 0)'))
                    ->where('accounting_entry', AccountingEntryEnum::DEBIT)
                    ->where('payment_instrument_id', $coinsPaymentInstrumentId)
                    ->where('transaction_status_id', $successTransactionStatusId);

                switch ($period) {
                    case 'this_month':
                        $query->whereBetween('created_at', [now('GMT+7')->startOfMonth()->subHours(7), now('GMT+7')->endOfMonth()->subHours(7)]);
                        break;
                    case 'this_week':
                        $query->whereBetween('created_at', [now('GMT+7')->startOfWeek()->subHours(7), now('GMT+7')->endOfWeek()->subHours(7)]);
                        break;
                    case 'today':
                        $query->whereBetween('created_at', [now('GMT+7')->startOfDay()->subHours(7), now('GMT+7')->endOfDay()->subHours(7)]);
                        break;
                }
            },
        ]);
    }

    function scopeWithCoinsCredit($query, $period)
    {
        $coinsPaymentInstrumentId = PaymentInstrument::where('slug', PaymentInstrumentEnum::COINS)->first()->id;
        $successTransactionStatusId = TransactionStatus::where('slug', TransactionStatusEnum::SUCCESS)->first()->id;

        return $query->withCount([
            "transactions AS coins_credit_$period" => function ($query) use ($period, $coinsPaymentInstrumentId, $successTransactionStatusId) {
                $query->select(DB::raw('COALESCE(SUM(amount), 0)'))
                    ->where('accounting_entry', AccountingEntryEnum::CREDIT)
                    ->where('payment_instrument_id', $coinsPaymentInstrumentId)
                    ->where('transaction_status_id', $successTransactionStatusId);

                switch ($period) {
                    case 'this_month':
                        $query->whereBetween('created_at', [now('GMT+7')->startOfMonth()->subHours(7), now('GMT+7')->endOfMonth()->subHours(7)]);
                        break;
                    case 'this_week':
                        $query->whereBetween('created_at', [now('GMT+7')->startOfWeek()->subHours(7), now('GMT+7')->endOfWeek()->subHours(7)]);
                        break;
                    case 'today':
                        $query->whereBetween('created_at', [now('GMT+7')->startOfDay()->subHours(7), now('GMT+7')->endOfDay()->subHours(7)]);
                        break;
                }
            },
        ]);
    }
}
