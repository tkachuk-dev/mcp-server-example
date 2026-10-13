<?php

declare(strict_types=1);

namespace App\Enums;

enum UserProjectionStatus: string
{
    case Active = 'active';
    case Blocked = 'blocked';
    case Deleted = 'deleted';
}
