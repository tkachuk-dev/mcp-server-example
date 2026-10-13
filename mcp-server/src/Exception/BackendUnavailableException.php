<?php

declare(strict_types=1);

namespace App\Exception;

use App\Enums\BackendService;

final class BackendUnavailableException extends BackendException
{
    public function __construct(BackendService $service, ?\Throwable $previous = null)
    {
        parent::__construct($service->unavailableMessage(), 0, $previous);
    }
}
