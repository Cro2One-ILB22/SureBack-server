<?php

namespace App\Enums;

use App\Traits\EnumToArray;

enum StoryApprovalStatusEnum: int
{
  use EnumToArray;

  case REJECTED = 0;
  case APPROVED = 1;
  case REVIEW = 2;
}
