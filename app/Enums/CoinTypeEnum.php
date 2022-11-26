<?php

namespace App\Enums;

use App\Traits\EnumToArray;

enum CoinTypeEnum: string
{
    use EnumToArray;

    case GLOBAL = 'global';
    case LOCAL = 'local';

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
            self::GLOBAL => 'Global',
            self::LOCAL => 'Local',
        };
    }
}
