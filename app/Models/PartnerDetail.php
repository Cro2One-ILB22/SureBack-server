<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerDetail extends Model
{
    use HasFactory;

    protected $appends = [
        'todays_token_count',
    ];

    public function todaysTokenCount(): Attribute
    {
        return new Attribute(
            fn () => $this->user->storyTokens
                ->where('created_at', '>=', now('Asia/Jakarta')->startOfDay())
                ->count()
        );
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
