<?php

declare(strict_types=1);

namespace App\Tests\Contract;

use PHPUnit\Framework\TestCase;

final class NoPiiInFixturesTest extends TestCase
{
    private const array FORBIDDEN_KEYS = [
        'email', 'phone', 'name', 'first_name', 'last_name', 'firstname', 'lastname', 'address',
        'ip', 'user_agent', 'useragent', 'card', 'iban', 'token', 'password',
    ];

    public function testFixturesContainNoPiiKeys(): void
    {
        $files = glob(__DIR__.'/../Fixtures/*/*.json') ?: [];
        self::assertNotEmpty($files);

        foreach ($files as $file) {
            $data = json_decode((string) file_get_contents($file), true, flags: \JSON_THROW_ON_ERROR);
            $found = array_intersect($this->keys($data), self::FORBIDDEN_KEYS);
            self::assertSame([], array_values($found), basename(\dirname($file)).'/'.basename($file));
        }
    }

    /**
     * @return list<string>
     */
    private function keys(mixed $data): array
    {
        if (!\is_array($data)) {
            return [];
        }

        $keys = [];
        foreach ($data as $key => $value) {
            if (\is_string($key)) {
                $keys[] = strtolower($key);
            }
            array_push($keys, ...$this->keys($value));
        }

        return $keys;
    }
}
