<?php

namespace App\Models;

use App\Enums\AccountingEntryEnum;
use App\Enums\TransactionCategoryEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'user',
        'tokens',
        'transaction_category_id',
        'transaction_status_id',
        'payment_instrument_id',
        'cashback',
        'customerCoinExchange',
        'merchantCoinExchange',
    ];

    protected $appends = [
        'purchase',
    ];

    protected function purchase(): Attribute
    {
        return new Attribute(
            function () {
                if ($this->category->slug === TransactionCategoryEnum::COIN_EXCHANGE) {
                    if ($this->user->isCustomer()) {
                        $purchase = $this->customerCoinExchange->purchase;
                    } else if ($this->user->isMerchant()) {
                        $purchase = $this->merchantCoinExchange->purchase;
                    }
                }
                if ($this->category->slug === TransactionCategoryEnum::CASHBACK) {
                    $purchase = $this->cashback->story->token->purchase;
                }
                if ($this->category->slug === TransactionCategoryEnum::STORY) {
                    $purchase = $this->tokens->first()->purchase;
                }

                if (isset($purchase)) {
                    $user = $this->user->isCustomer() ? 'merchant' : 'customer';
                    return $purchase->load([$user, 'token.cashback']);
                }
            }
        );
    }

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

    public function tokens()
    {
        return $this->belongsToMany(StoryToken::class, 'token_transactions');
    }
}
