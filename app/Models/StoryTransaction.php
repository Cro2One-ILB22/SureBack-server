<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoryTransaction extends Model
{
    use HasFactory;

    public function token()
    {
        return $this->belongsTo(StoryToken::class);
    }

    public function transaction()
    {
        return $this->belongsTo(FinancialTransaction::class, 'transaction_id');
    }
}
