<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

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
        'story_tokens'
    ];

    protected function password(): Attribute
    {
        return new Attribute(
            set: fn ($value) => bcrypt($value)
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
        return $this->belongsToMany(UserDevice::class, 'user_user_devices');
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
        return $this->hasMany(FinancialTransaction::class);
    }

    public function storyTokens()
    {
        return $this->hasMany(StoryToken::class, 'merchant_id');
    }

    public function stories()
    {
        return $this->hasMany(CustomerStory::class, 'customer_id');
    }

    public function merchantDetail()
    {
        return $this->hasOne(MerchantDetail::class);
    }
}
