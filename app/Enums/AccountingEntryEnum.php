<?php

namespace App\Enums;

use App\Traits\EnumToArray;

enum AccountingEntryEnum: string
{
  use EnumToArray;

  case DEBIT = 'D';
  case CREDIT = 'C';
}
