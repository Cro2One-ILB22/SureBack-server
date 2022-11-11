<?php

namespace App\Models;

use App\Enums\TransactionStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionStatus extends Model
{
    use HasFactory;

    protected $casts = [
        'slug' => TransactionStatusEnum::class,
    ];

    protected $hidden = [
        'updated_at',
        'created_at',
    ];
}
