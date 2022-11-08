<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cashback extends Model
{
    use HasFactory;

    public function story()
    {
        return $this->belongsTo(CustomerStory::class);
    }

    public function transaction()
    {
        return $this->belongsTo(FinancialTransaction::class, 'financial_transaction_id');
    }
}
