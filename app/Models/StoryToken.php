<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoryToken extends Model
{
    use HasFactory;

    public function partner()
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function story()
    {
        return $this->hasOne(CustomerStory::class);
    }

    public function transaction()
    {
        return $this->belongsTo(StoryTransaction::class);
    }
}
