<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
    use HasFactory;

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
