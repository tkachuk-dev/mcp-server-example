<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enums\BackendService;

final readonly class ToolInvocation
{
    /**
     * @param array<string, int> $arguments
     */
    public function __construct(
        public string $tool,
        public BackendService $service,
        public array $arguments,
    ) {
    }
}
