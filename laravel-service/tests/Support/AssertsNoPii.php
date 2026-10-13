<?php

declare(strict_types=1);

namespace Tests\Support;

trait AssertsNoPii
{
    private const array PII_KEYS = [
        'email', 'phone', 'name', 'first_name', 'last_name', 'firstName', 'lastName', 'address', 'card', 'iban',
    ];

    /**
     * @param  array<mixed>  $payload
     */
    protected function assertNoPiiKeys(array $payload): void
    {
        foreach ($payload as $key => $value) {
            $this->assertNotContains($key, self::PII_KEYS, "PII key '{$key}' found in response");

            if (is_array($value)) {
                $this->assertNoPiiKeys($value);
            }
        }
    }
}
