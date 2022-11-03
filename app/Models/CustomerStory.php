<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
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

    protected $appends = [
        'story_url',
    ];

    public function storyUrl(): Attribute
    {
        return new Attribute(
            fn () => $this->instagram_story_id ?
                "https://www.instagram.com/stories/{$this->customer->instagram_username}/{$this->instagram_story_id}/" :
                null
        );
    }

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
