<?php

namespace App\Models;

use App\Enums\VariableCategoryEnum;
use App\Enums\VariableEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Variable extends Model
{
    use HasFactory;

    protected $casts = [
        'key' => VariableEnum::class,
    ];

    public function key(): Attribute
    {
        return new Attribute(
            fn ($value) => $value,
        );
    }

    public function categories()
    {
        return $this->belongsToMany(VariableCategory::class, 'variable_categorizations');
    }
}
