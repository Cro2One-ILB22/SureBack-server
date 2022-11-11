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
        'code',
        'instagram_id',
        'expires_at',
        'purchase_id',
    ];

    protected $hidden = [
        'purchase_id',
    ];

    protected $casts = [
        'instagram_id' => 'integer',
    ];

    protected $appends = [
        'merchant',
    ];

    protected function code(): Attribute
    {
        return new Attribute(
            fn ($value) => CryptoService::decrypt($value),
            fn ($value) => CryptoService::encrypt($value)
        );
    }

    public function merchant(): Attribute
    {
        return new Attribute(
            fn () => $this->purchase->merchant,
        );
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }

    public function transactions()
    {
        return $this->belongsToMany(FinancialTransaction::class, 'token_transactions');
    }

    public function story()
    {
        return $this->hasOne(CustomerStory::class);
    }

    public function tokenCashback()
    {
        return $this->hasOne(TokenCashback::class, 'token_id');
    }
}
