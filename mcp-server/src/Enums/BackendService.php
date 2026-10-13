<?php

declare(strict_types=1);

namespace App\Enums;

enum BackendService: string
{
    case Users = 'users';
    case Billing = 'billing';

    public function unavailableMessage(): string
    {
        return \sprintf('Сервіс %s тимчасово недоступний', $this->value);
    }
}
