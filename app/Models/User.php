<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Services\DropboxService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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
}
