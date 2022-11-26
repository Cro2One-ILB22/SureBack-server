<?php

namespace App\Enums;

use App\Traits\EnumToArray;

enum CashbackCalculationMethodEnum: string
{
    use EnumToArray;

    case PURCHASE_AMOUNT = 'purchase_amount';
    case PAYMENT_AMOUNT = 'payment_amount';

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
            self::PURCHASE_AMOUNT => 'Purchase Amount',
            self::PAYMENT_AMOUNT => 'Payment Amount',
        };
    }
}
