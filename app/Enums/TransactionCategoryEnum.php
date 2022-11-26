<?php

namespace App\Enums;

use App\Traits\EnumToArray;

enum TransactionCategoryEnum: string
{
    use EnumToArray;

    case DEPOSIT = 'deposit';
    case CASHBACK = 'cashback';
    case WITHDRAWAL = 'withdrawal';
    case STORY = 'story';
    case PURCHASE = 'purchase';
    case COIN_EXCHANGE = 'coin_exchange';

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
            self::DEPOSIT => 'Deposit',
            self::CASHBACK => 'Cashback',
            self::WITHDRAWAL => 'Withdrawal',
            self::STORY => 'Story',
            self::PURCHASE => 'Purchase',
            self::COIN_EXCHANGE => 'Coin Exchange',
        };
    }
}
