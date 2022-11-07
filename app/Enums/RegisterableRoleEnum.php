<?php

namespace App\Enums;

use App\Traits\EnumToArray;

enum RegisterableRoleEnum: string
{
  use EnumToArray;

  case MERCHANT = 'merchant';
  case CUSTOMER = 'customer';
}
