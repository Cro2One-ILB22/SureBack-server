<?php

namespace App\Enums;

use App\Traits\EnumToArray;

enum CashbackTypeEnum: string
{
  use EnumToArray;

  case GLOBAL = 'global';
  case LOCAL = 'local';
}
