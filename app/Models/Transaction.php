<?php

namespace App\Models;

use App\Enums\AccountingEntryEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'amount',
        'description',
        'accounting_entry',
        'user_id',
        'transaction_category_id',
        'transaction_status_id',
        'payment_instrument_id',
        // 'reference',
        // 'reference_type',
        // 'reference_id',
    ];

    protected $casts = [
        'amount' => 'integer',
        'accounting_entry' => AccountingEntryEnum::class,
    ];

    protected $hidden = [
        'user_id',
        'transaction_category_id',
        'transaction_status_id',
        'payment_instrument_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(TransactionCategory::class, 'transaction_category_id');
    }

    public function status()
    {
        return $this->belongsTo(TransactionStatus::class, 'transaction_status_id');
    }

    public function paymentInstrument()
    {
        return $this->belongsTo(PaymentInstrument::class);
    }

    public function withdrawal()
    {
        return $this->hasOne(Withdrawal::class);
    }

    public function ledgers()
    {
        return $this->hasMany(Ledger::class);
    }

    public function corporateLedger()
    {
        return $this->hasOne(CorporateLedger::class);
    }

    public function cashback()
    {
        return $this->hasOne(Cashback::class);
    }

    public function deposit()
    {
        return $this->hasOne(Deposit::class);
    }

    public function customerPurchase()
    {
        return $this->hasOne(Purchase::class, 'customer_transaction_id');
    }

    public function merchantPurchase()
    {
        return $this->hasOne(Purchase::class, 'merchant_transaction_id');
    }

    public function customerCoinExchange()
    {
        return $this->hasOne(CoinExchange::class, 'customer_transaction_id');
    }

    public function merchantCoinExchange()
    {
        return $this->hasOne(CoinExchange::class, 'merchant_transaction_id');
    }
}
