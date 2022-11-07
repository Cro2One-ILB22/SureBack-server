<?php

namespace App\Models;

use App\Enums\InstagramStoryStatusEnum;
use App\Enums\StoryApprovalStatusEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerStory extends Model
{
    use HasFactory;

    protected $fillable = [
        'instagram_id',
        'image_uri',
        'approval_status',
        'instagram_story_status',
        'submitted_at',
    ];

    protected $hidden = [
        'story_token_id',
        'customer_id',
    ];

    protected $appends = [
        'story_url',
    ];

    protected $casts = [
        'instagram_id' => 'string',
        'image_uri' => 'string',
        'approval_status' => StoryApprovalStatusEnum::class,
        'instagram_story_status' => InstagramStoryStatusEnum::class,
    ];

    public function approvalStatus(): Attribute
    {
        return new Attribute(
            set: fn ($value) => strval($value->value),
        );
    }

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
