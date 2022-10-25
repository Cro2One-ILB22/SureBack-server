<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorporateTransaction extends Model
{
    use HasFactory;

    public function financialTransaction()
    {
        return $this->belongsTo(FinancialTransaction::class);
    }
}
