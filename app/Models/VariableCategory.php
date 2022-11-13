<?php

namespace App\Models;

use App\Enums\VariableCategoryEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VariableCategory extends Model
{
    use HasFactory;

    protected $casts = [
        'slug' => VariableCategoryEnum::class,
    ];

    public function variables()
    {
        return $this->belongsToMany(Variable::class, 'variable_categorizations');
    }
}
