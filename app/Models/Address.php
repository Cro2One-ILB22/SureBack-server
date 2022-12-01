<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'addressable_id',
        'addressable_type',
        'location_id',
    ];

    protected $hidden = [
        'addressable_id',
        'addressable_type',
        'location_id',
    ];

    public function addressable()
    {
        return $this->morphTo();
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
