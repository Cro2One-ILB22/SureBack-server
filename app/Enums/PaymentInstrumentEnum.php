<?php

namespace App\Enums;

use App\Traits\EnumToArray;

enum PaymentInstrumentEnum: string
{
  use EnumToArray;

  case COINS = 'coins';
  case BALANCE = 'balance';
}
