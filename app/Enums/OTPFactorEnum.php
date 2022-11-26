<?php

namespace App\Enums;

use App\Traits\EnumToArray;

enum OTPFactorEnum: string
{
    use EnumToArray;

    case EMAIL = 'email';
    case SMS = 'sms';
    case INSTAGRAM = 'instagram';
}
