<?php

namespace App\Models;

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

    public function getChangeAttribute(): int
    {
        return $this->after - $this->before;
    }

    public function transaction()
    {
        return $this->belongsTo(FinancialTransaction::class, 'financial_transaction_id');
    }
}