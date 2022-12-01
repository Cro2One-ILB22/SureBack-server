<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    private $haversine = '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))';

    protected $fillable = [
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'distance' => 'float',
    ];

    function scopeWithDistance($query, $latitude, $longitude)
    {
        $query = $query->selectRaw("*, {$this->haversine} AS distance", [$latitude, $longitude, $latitude]);
        return $query;
    }

    function scopeDistance($query, $latitude, $longitude, $radius)
    {
        if ($radius) {
            return $query->whereRaw("{$this->haversine} < ?", [$latitude, $longitude, $latitude, $radius]);
        }

        return $query;
    }

    public function address()
    {
        return $this->morphOne(Address::class, 'addressable');
    }
}
