<?php

namespace App\Enums;

use App\Traits\EnumToArray;

enum InstagramStoryStatusEnum: string
{
  use EnumToArray;

  case UPLOADED = 'uploaded';
  case VALIDATED = 'validated';
  case DELETED = 'deleted';
}
