<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class OpenApiReferencesTest extends TestCase
{
    private const string SPEC_DIR = __DIR__.'/../../public/openapi';

    public function testEveryReferenceResolves(): void
    {
        $files = glob(self::SPEC_DIR.'/*.yaml') ?: [];
        self::assertNotEmpty($files);

        foreach ($files as $file) {
            $this->assertReferencesResolve(Yaml::parseFile($file), basename($file));
        }
    }

    public function testInternalAiPathsAreRegisteredInEntryPoint(): void
    {
        $entry = Yaml::parseFile(self::SPEC_DIR.'/openapi.yaml');
        $internal = Yaml::parseFile(self::SPEC_DIR.'/internal-ai.yaml');

        foreach (array_keys($internal['paths']) as $path) {
            self::assertArrayHasKey($path, $entry['paths']);
            self::assertSame(['get'], array_keys($internal['paths'][$path]), 'Internal AI API is GET-only');
        }
    }

    private function assertReferencesResolve(mixed $node, string $currentFile): void
    {
        if (!\is_array($node)) {
            return;
        }

        foreach ($node as $key => $value) {
            if ('$ref' === $key && \is_string($value)) {
                $this->assertReferenceResolves($value, $currentFile);
                continue;
            }
            $this->assertReferencesResolve($value, $currentFile);
        }
    }

    private function assertReferenceResolves(string $ref, string $currentFile): void
    {
        [$file, $pointer] = array_pad(explode('#', $ref, 2), 2, '');
        $target = Yaml::parseFile(self::SPEC_DIR.'/'.('' === $file ? $currentFile : ltrim($file, './')));

        foreach (array_filter(explode('/', $pointer), static fn (string $s): bool => '' !== $s) as $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], rawurldecode($segment));
            self::assertIsArray($target, sprintf('Unresolvable $ref "%s" in %s', $ref, $currentFile));
            self::assertArrayHasKey($segment, $target, sprintf('Unresolvable $ref "%s" in %s', $ref, $currentFile));
            $target = $target[$segment];
        }
    }
}
