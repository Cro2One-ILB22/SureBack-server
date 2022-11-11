<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_amount',
        'payment_amount',
        'merchant_id',
        'customer_transaction_id',
        'merchant_transaction_id',
    ];

    protected $hidden = [
        'customer_id',
        'merchant_id',
        'customer_transaction_id',
        'merchant_transaction_id',
    ];

    protected $casts = [
        'purchase_amount' => 'integer',
        'payment_amount' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    public function customerTransaction()
    {
        return $this->belongsTo(FinancialTransaction::class, 'customer_transaction_id');
    }

    public function merchantTransaction()
    {
        return $this->belongsTo(FinancialTransaction::class, 'merchant_transaction_id');
    }

    public function coinExchange()
    {
        return $this->hasOne(CoinExchange::class, 'purchase_id');
    }

    public function token()
    {
        return $this->hasOne(StoryToken::class, 'purchase_id');
    }
}
