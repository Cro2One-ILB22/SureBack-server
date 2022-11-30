<?php

namespace App\Models;

use App\Enums\InstagramStoryStatusEnum;
use App\Enums\StoryApprovalStatusEnum;
use App\Services\DropboxService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerStory extends Model
{
    use HasFactory;

    protected $fillable = [
        'instagram_story_id',
        'instagram_id',
        'image_uri',
        'video_uri',
        'approval_status',
        'instagram_story_status',
        'submitted_at',
        'assessed_at',
        'inspected_at',
        'expiring_at',
    ];

    protected $hidden = [
        'story_token_id',
        'customer_id',
    ];

    protected $appends = [
        'story_url',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['assessed_at', 'inspected_at', 'submitted_at'];

    protected $casts = [
        'instagram_story_id' => 'integer',
        'instagram_id' => 'integer',
        'image_uri' => 'string',
        'approval_status' => StoryApprovalStatusEnum::class,
        'instagram_story_status' => InstagramStoryStatusEnum::class,
        'expiring_at' => 'integer',
    ];

    protected function imageUri(): Attribute
    {
        return new Attribute(
            fn ($value) => $value ? ((new DropboxService())->getTempImageLink("stories/{$value}") ?? $value) : $value
        );
    }

    protected function videoUri(): Attribute
    {
        return new Attribute(
            fn ($value) => $value ? ((new DropboxService())->getTempVideoLink("stories/{$value}") ?? $value) : $value
        );
    }

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
