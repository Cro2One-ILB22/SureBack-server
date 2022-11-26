<?php

namespace App\Enums;

use App\Traits\EnumToArray;

enum PaymentInstrumentEnum: string
{
    use EnumToArray;

    case COINS = 'coins';
    case BALANCE = 'balance';
    case OTHER = 'other';

    public static function fullNames(): array
    {
        return array_combine(
            array_map(fn ($value) => $value, self::values()),
            array_map(fn ($case) => $case->dynamicFullNames(), self::cases())
        );
    }

    private function dynamicFullNames(): string
    {
        return match ($this) {
            self::COINS => 'Coins',
            self::BALANCE => 'Balance',
            self::OTHER => 'Other',
        };
    }
}
