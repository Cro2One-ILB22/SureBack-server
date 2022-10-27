<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuccessfulTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'balance_before',
        'balance_after',
        'points_before',
        'points_after',
    ];

    public function transaction()
    {
        return $this->belongsTo(FinancialTransaction::class, 'financial_transaction_id');
    }
}
