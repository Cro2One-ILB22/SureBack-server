<?php

namespace App\Models;

use App\Enums\AccountingEntryEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorporateLedger extends Model
{
    use HasFactory;

    protected $fillable = [
        'amount',
        'accounting_entry',
        'balance_before',
        'balance_after',
    ];

    protected $casts = [
        'amount' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
        'accounting_entry' => AccountingEntryEnum::class,
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
