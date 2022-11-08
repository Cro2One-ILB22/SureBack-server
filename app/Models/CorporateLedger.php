<?php

namespace App\Models;

use App\Enums\TransactionTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorporateLedger extends Model
{
    use HasFactory;

    protected $fillable = [
        'amount',
        'type',
        'balance_before',
        'balance_after',
    ];

    protected $casts = [
        'amount' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
        'type' => TransactionTypeEnum::class,
    ];

    public function financialTransaction()
    {
        return $this->belongsTo(FinancialTransaction::class);
    }
}
