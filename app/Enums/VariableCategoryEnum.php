<?php

namespace App\Enums;

use App\Traits\EnumToArray;

enum VariableCategoryEnum: string
{
    use EnumToArray;

    case IG = 'ig';
    case IG_USERNAME = 'ig_username';
    case IG_URL = 'ig_url';
    case IG_WS = 'ig_ws';
    case IG_HEADER = 'ig_header';
    case IG_COOKIE = 'ig_cookie';

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
            self::IG => 'Instagram',
            self::IG_USERNAME => 'Instagram Username',
            self::IG_URL => 'Instagram URL',
            self::IG_WS => 'Instagram WS',
            self::IG_HEADER => 'Instagram Header',
            self::IG_COOKIE => 'Instagram Cookie',
        };
    }
}
