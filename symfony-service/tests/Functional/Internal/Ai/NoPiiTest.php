<?php

declare(strict_types=1);

namespace App\Tests\Functional\Internal\Ai;

use App\Tests\Support\InternalAiWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class NoPiiTest extends InternalAiWebTestCase
{
    private const array FORBIDDEN_KEYS = [
        'email', 'phone', 'name', 'first_name', 'firstName', 'last_name', 'lastName',
        'ip', 'user_agent', 'userAgent', 'password', 'token', 'address',
    ];

    #[DataProvider('endpoints')]
    public function testResponseContainsNoPersonalData(string $endpoint): void
    {
        $response = $this->mcpGet(self::BASE_PATH.'/1/'.$endpoint);

        self::assertSame(200, $response->getStatusCode());
        $this->assertNoKeysRecursive(self::FORBIDDEN_KEYS, $this->json($response));
        self::assertStringNotContainsString('@acme.test', (string) $response->getContent());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function endpoints(): iterable
    {
        yield 'users-summary' => ['users-summary'];
        yield 'login-activity' => ['login-activity'];
        yield 'security-events' => ['security-events'];
    }
}
