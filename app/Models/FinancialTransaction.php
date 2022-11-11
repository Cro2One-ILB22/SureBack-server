<?php

namespace App\Models;

use App\Enums\TransactionTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'amount',
        'description',
        'type',
        'user_id',
        'transaction_category_id',
        'transaction_status_id',
        // 'reference',
        // 'reference_type',
        // 'reference_id',
    ];

    protected $casts = [
        'amount' => 'integer',
        'type' => TransactionTypeEnum::class,
    ];

    protected $hidden = [
        'user_id',
        'transaction_category_id',
        'transaction_status_id',
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
}
