<?php

declare(strict_types=1);

namespace App\Exception;

final class InvalidArgumentsException extends BackendException
{
    public function __construct(string $parameter, ?\Throwable $previous = null)
    {
        parent::__construct(\sprintf('Невірні параметри: %s', $parameter), 0, $previous);
    }
}
