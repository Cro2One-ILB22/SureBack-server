<?php

namespace App\Models;

use App\Enums\TransactionCategoryEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionCategory extends Model
{
    use HasFactory;

    protected $hidden = [
        'updated_at',
        'created_at',
    ];

    protected $casts = [
        'slug' => TransactionCategoryEnum::class,
    ];
}
