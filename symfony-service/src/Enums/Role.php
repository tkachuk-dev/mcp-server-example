<?php

declare(strict_types=1);

namespace App\Enums;

enum Role: int
{
    case User = 1;
    case Manager = 2;
    case Admin = 3;

    public function securityRole(): string
    {
        return match ($this) {
            self::User => 'ROLE_USER',
            self::Manager => 'ROLE_MANAGER',
            self::Admin => 'ROLE_ADMIN',
        };
    }
}
