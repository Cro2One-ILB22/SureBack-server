<?php

namespace App\Models;

use App\Enums\PaymentInstrumentEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ledger extends Model
{
    use HasFactory;

    protected $fillable = [
        'before',
        'after',
    ];

    protected $appends = [
        'change',
    ];

    protected $casts = [
        'before' => 'integer',
        'after' => 'integer',
        'instrument' => PaymentInstrumentEnum::class,
    ];

    public function getChangeAttribute(): int
    {
        return $this->after - $this->before;
    }

    public function transaction()
    {
        return $this->belongsTo(FinancialTransaction::class, 'financial_transaction_id');
    }

    public function instrument()
    {
        return $this->belongsTo(PaymentInstrument::class, 'payment_instrument_id');
    }
}
