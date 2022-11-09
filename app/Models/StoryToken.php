<?php

namespace App\Models;

use App\Services\CryptoService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoryToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'purchase_amount',
        'instagram_id',
        'expires_at',
    ];

    protected $hidden = [
        'merchant_id'
    ];

    protected $casts = [
        'purchase_amount' => 'integer',
        'instagram_id' => 'integer',
    ];

    protected function token(): Attribute
    {
        return new Attribute(
            fn ($value) => CryptoService::decrypt($value),
            fn ($value) => CryptoService::encrypt($value)
        );
    }

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function story()
    {
        return $this->hasOne(CustomerStory::class);
    }

    public function transactions()
    {
        return $this->belongsToMany(FinancialTransaction::class, 'story_transactions');
    }

    public function cashback()
    {
        return $this->hasOne(TokenCashback::class, 'token_id');
    }
}
