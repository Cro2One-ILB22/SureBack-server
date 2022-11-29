<?php

namespace App\Models;

use App\Services\CryptoService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'owner',
        'expires_at',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['expires_at'];

    protected function code(): Attribute
    {
        return new Attribute(
            fn ($value) => CryptoService::decrypt($value),
            fn ($value) => CryptoService::encrypt($value)
        );
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function factor()
    {
        return $this->belongsTo(OtpFactor::class);
    }
}
