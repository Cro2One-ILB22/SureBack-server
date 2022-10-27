<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoryToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'expires_at',
    ];

    protected $appends = [
        'cashback_amount',
    ];

    protected $hidden = [
        'partner_id'
    ];

    public function partner()
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function story()
    {
        return $this->hasOne(CustomerStory::class);
    }

    public function cashbackAmount(): Attribute
    {
        return new Attribute(
            fn () => $this->transactions()->first()->amount
        );
    }

    public function transactions()
    {
        return $this->belongsToMany(FinancialTransaction::class, 'story_transactions');
    }
}
