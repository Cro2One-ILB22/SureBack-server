<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerStory extends Model
{
    use HasFactory;

    protected $fillable = [
        'instagram_id'
    ];

    protected $hidden = [
        'story_token_id',
        'customer_id',
    ];

    public function token()
    {
        return $this->belongsTo(StoryToken::class, 'story_token_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function cashback()
    {
        return $this->hasOne(Cashback::class, 'story_id');
    }
}
