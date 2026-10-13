<?php

declare(strict_types=1);

namespace App\Enums;

enum LoginResult: int
{
    case Success = 1;
    case Failure = 2;
}
