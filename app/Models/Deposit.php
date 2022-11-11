<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deposit extends Model
{
    use HasFactory;

    public function destinationAccount()
    {
        return $this->belongsTo(CorporateAccount::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
