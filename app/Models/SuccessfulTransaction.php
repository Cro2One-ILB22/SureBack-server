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

    protected $appends = [
        'balance_change',
        'points_change',
    ];

    public function getBalanceChangeAttribute(): int
    {
        return $this->balance_after - $this->balance_before;
    }

    public function getPointsChangeAttribute(): int
    {
        return $this->points_after - $this->points_before;
    }

    public function transaction()
    {
        return $this->belongsTo(FinancialTransaction::class, 'financial_transaction_id');
    }
}
