<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\Yaml\Yaml;

/**
 * Minimal OpenAPI 3.1 reader for contract tests: follows the entry document's paths and resolves
 * every $ref (relative files + JSON pointers) into a self-contained JSON Schema.
 */
final class OpenApiDocument
{
    /** @var array<string, \stdClass> */
    private array $files = [];

    public function __construct(
        private readonly string $entryFile,
    ) {
    }

    public function responseSchema(string $pathSuffix, int $status, string $method = 'get'): \stdClass
    {
        $entry = $this->load($this->entryFile);
        foreach (get_object_vars($entry->paths) as $path => $item) {
            if (!str_ends_with($path, $pathSuffix)) {
                continue;
            }

            $operation = $this->resolve($item, $this->entryFile)->{$method};
            $response = $operation->responses->{(string) $status} ?? throw new \LogicException(\sprintf('No %d response for %s', $status, $path));

            return $response->content->{'application/json'}->schema;
        }

        throw new \LogicException(\sprintf('Path *%s not found in %s', $pathSuffix, $this->entryFile));
    }

    private function resolve(mixed $node, string $file): mixed
    {
        if (\is_array($node)) {
            return array_map(fn (mixed $child): mixed => $this->resolve($child, $file), $node);
        }

        if (!$node instanceof \stdClass) {
            return $node;
        }

        if (isset($node->{'$ref'}) && \is_string($node->{'$ref'})) {
            [$target, $targetFile] = $this->follow($node->{'$ref'}, $file);

            return $this->resolve($target, $targetFile);
        }

        $resolved = new \stdClass();
        foreach (get_object_vars($node) as $key => $value) {
            $resolved->{$key} = $this->resolve($value, $file);
        }

        return $resolved;
    }

    /**
     * @return array{mixed, string}
     */
    private function follow(string $ref, string $file): array
    {
        [$relative, $pointer] = array_pad(explode('#', $ref, 2), 2, '');
        $targetFile = '' === $relative ? $file : \dirname($file).'/'.$relative;
        $node = $this->load($targetFile);

        foreach (array_filter(explode('/', $pointer), static fn (string $part): bool => '' !== $part) as $part) {
            $key = str_replace(['~1', '~0'], ['/', '~'], rawurldecode($part));
            $node = $node instanceof \stdClass ? ($node->{$key} ?? null) : null;
            if (null === $node) {
                throw new \LogicException(\sprintf('Unresolvable $ref %s in %s', $ref, $file));
            }
        }

        return [$node, $targetFile];
    }

    private function load(string $file): \stdClass
    {
        $path = realpath($file) ?: $file;
        if (!isset($this->files[$path])) {
            $parsed = Yaml::parseFile($path, Yaml::PARSE_OBJECT_FOR_MAP);
            if (!$parsed instanceof \stdClass) {
                throw new \LogicException('Not an OpenAPI document: '.$path);
            }
            $this->files[$path] = $parsed;
        }

        return $this->files[$path];
    }
}
