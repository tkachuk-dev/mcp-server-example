<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp;

use App\Mcp\Tool\BillingTools;
use App\Mcp\Tool\UsersTools;
use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ToolConventionsTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function toolClasses(): iterable
    {
        yield 'users' => [UsersTools::class, 'users'];
        yield 'billing' => [BillingTools::class, 'billing'];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('toolClasses')]
    public function testEveryPublicMethodIsOneWellDescribedTool(string $class, string $service): void
    {
        $methods = array_filter(
            (new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC),
            static fn (\ReflectionMethod $method): bool => !$method->isConstructor(),
        );
        self::assertCount(3, $methods);

        foreach ($methods as $method) {
            $attributes = $method->getAttributes(McpTool::class);
            self::assertCount(1, $attributes, $method->getName());
            $tool = $attributes[0]->newInstance();

            self::assertMatchesRegularExpression('/^'.$service.'_get_[a-z_]+$/', (string) $tool->name);
            self::assertStringContainsString(ucfirst($service).' service', (string) $tool->description);
            self::assertStringContainsString('PII', (string) $tool->description);
            self::assertTrue($tool->annotations?->readOnlyHint);
        }
    }
}
