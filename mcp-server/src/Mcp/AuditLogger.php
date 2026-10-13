<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Dto\ToolInvocation;
use App\Enums\AuditResult;
use App\Security\ActingUser;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel(self::CHANNEL)]
final readonly class AuditLogger
{
    public const string CHANNEL = 'audit';
    public const string MESSAGE = 'tools/call';

    public function __construct(
        private LoggerInterface $logger,
        private ActingUser $actingUser,
    ) {
    }

    public function log(ToolInvocation $invocation, AuditResult $result, int $durationMs): void
    {
        $this->logger->info(self::MESSAGE, [
            'email' => $this->actingUser->email(),
            'tool' => $invocation->tool,
            'service' => $invocation->service->value,
            'arguments' => $invocation->arguments,
            'result_status' => $result->value,
            'duration_ms' => $durationMs,
        ]);
    }
}
