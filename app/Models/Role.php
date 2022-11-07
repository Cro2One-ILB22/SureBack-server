<?php

namespace App\Models;

use App\Enums\RoleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $hidden = [
        'id',
        'pivot',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'slug' => RoleEnum::class,
    ];
}
