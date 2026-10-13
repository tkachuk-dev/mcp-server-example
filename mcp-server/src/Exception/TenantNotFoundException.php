<?php

declare(strict_types=1);

namespace App\Exception;

final class TenantNotFoundException extends BackendException
{
    public const string MESSAGE = 'Tenant не знайдено';

    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(self::MESSAGE, 0, $previous);
    }
}
