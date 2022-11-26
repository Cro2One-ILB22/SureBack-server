<?php

namespace App\Enums;

use App\Traits\EnumToArray;

enum RoleEnum: string
{
    use EnumToArray;

    case SUPER_ADMIN = 'super_admin';
    case ADMIN = 'admin';
    case USER = 'user';
    case MERCHANT = 'merchant';
    case CUSTOMER = 'customer';

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
            self::SUPER_ADMIN => 'Super Admin',
            self::ADMIN => 'Admin',
            self::USER => 'User',
            self::MERCHANT => 'Merchant',
            self::CUSTOMER => 'Customer',
        };
    }
}
