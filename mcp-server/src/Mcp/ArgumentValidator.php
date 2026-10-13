<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Exception\InvalidArgumentsException;

/**
 * Ranges are checked here rather than via #[Schema(minimum/maximum)]: a schema violation is
 * answered by the SDK as a JSON-RPC -32602 in English, while the model should get "isError" with
 * a short message naming the parameter.
 */
final class ArgumentValidator
{
    public const string TENANT_ID = 'tenantId';
    public const int MIN_TENANT_ID = 1;

    /**
     * @throws InvalidArgumentsException
     */
    public function assertTenantId(int $tenantId): void
    {
        $this->assertInRange(self::TENANT_ID, $tenantId, self::MIN_TENANT_ID, \PHP_INT_MAX);
    }

    /**
     * @throws InvalidArgumentsException
     */
    public function assertInRange(string $name, int $value, int $min, int $max): void
    {
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentsException($name);
        }
    }
}
