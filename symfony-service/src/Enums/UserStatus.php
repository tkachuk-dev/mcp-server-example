<?php

declare(strict_types=1);

namespace App\Enums;

enum UserStatus: int
{
    case Active = 1;
    case Blocked = 2;
    case Deleted = 3;
}
