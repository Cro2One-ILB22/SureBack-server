<?php

namespace App\Enums;

use App\Traits\EnumToArray;

enum TransactionTypeEnum: string
{
  use EnumToArray;

  case DEBIT = 'D';
  case CREDIT = 'C';
}
