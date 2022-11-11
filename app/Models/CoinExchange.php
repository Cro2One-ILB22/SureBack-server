<?php

namespace App\Models;

use App\Enums\CoinTypeEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoinExchange extends Model
{
    use HasFactory;

    protected $fillable = [
        'coin_type',
    ];

    protected $hidden = [
        'customer_transaction_id',
        'merchant_transaction_id',
        'purchase_id',
        'customerTransaction',
        'merchantTransaction',
    ];

    protected $casts = [
        'amount' => 'integer',
        'coin_type' => CoinTypeEnum::class,
    ];

    protected $appends = [
        'amount',
    ];

    protected function amount(): Attribute
    {
        return new Attribute(
            fn () => $this->customerTransaction->amount ?? $this->merchantTransaction->amount
        );
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }

    public function customerTransaction()
    {
        return $this->belongsTo(Transaction::class, 'customer_transaction_id');
    }

    public function merchantTransaction()
    {
        return $this->belongsTo(Transaction::class, 'merchant_transaction_id');
    }
}
