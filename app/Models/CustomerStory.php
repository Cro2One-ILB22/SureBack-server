<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerStory extends Model
{
    use HasFactory;

    public function storyToken()
    {
        return $this->belongsTo(StoryToken::class, 'customer_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class);
    }

    public function cashback()
    {
        return $this->hasOne(Cashback::class, 'story_id');
    }
}
