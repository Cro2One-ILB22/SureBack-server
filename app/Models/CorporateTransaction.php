<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorporateTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'amount',
        'type',
        'balance_before',
        'balance_after',
    ];

    public function financialTransaction()
    {
        return $this->belongsTo(FinancialTransaction::class);
    }
}
