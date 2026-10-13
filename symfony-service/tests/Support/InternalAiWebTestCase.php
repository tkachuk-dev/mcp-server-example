<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Opis\JsonSchema\Validator;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;

abstract class InternalAiWebTestCase extends WebTestCase
{
    protected const string SERVICE_TOKEN = 'test-mcp-token';
    protected const string ROTATED_SERVICE_TOKEN = 'test-mcp-token-rotated';
    protected const string ACTING_USER = 'alice@example.com';
    protected const string BASE_PATH = '/internal/ai/v1/tenants';
    protected const int TENANT_ID = 1;
    protected const int OTHER_TENANT_ID = 2;
    protected const int MISSING_TENANT_ID = 999;

    private const string COMPONENTS_ID = 'urn:users-service:components';
    private const string COMPONENTS_FILE = '/public/openapi/components.yaml';

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * @param array<string, string> $headers extra headers, e.g. ['HTTP_X_REQUEST_ID' => '...']
     */
    protected function mcpGet(string $uri, array $headers = [], string $token = self::SERVICE_TOKEN): Response
    {
        $this->client->request('GET', $uri, server: $headers + [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_X_ACTING_USER' => self::ACTING_USER,
        ]);

        return $this->client->getResponse();
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(Response $response): array
    {
        self::assertStringStartsWith('application/json', (string) $response->headers->get('Content-Type'));

        /* @var array<string, mixed> */
        return json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    protected function assertMatchesSchema(string $schemaName, Response $response): void
    {
        $validator = new Validator();
        $validator->resolver()?->registerRaw($this->componentsDocument(), self::COMPONENTS_ID);

        $data = json_decode((string) $response->getContent(), false, flags: \JSON_THROW_ON_ERROR);
        $result = $validator->validate($data, (object) ['$ref' => self::COMPONENTS_ID.'#/components/schemas/'.$schemaName]);

        self::assertTrue($result->isValid(), sprintf('Response does not match OpenAPI schema "%s": %s', $schemaName, json_encode($result->error()?->keyword()).' '.json_encode($result->error()?->args())));
    }

    /**
     * @param list<string> $forbiddenKeys
     */
    protected function assertNoKeysRecursive(array $forbiddenKeys, mixed $data, string $path = '$'): void
    {
        if (!\is_array($data)) {
            return;
        }

        foreach ($data as $key => $value) {
            self::assertNotContains($key, $forbiddenKeys, sprintf('Forbidden key "%s" found at %s', $key, $path));
            $this->assertNoKeysRecursive($forbiddenKeys, $value, $path.'.'.$key);
        }
    }

    private function componentsDocument(): object
    {
        $yaml = Yaml::parseFile(static::getContainer()->getParameter('kernel.project_dir').self::COMPONENTS_FILE);

        return json_decode(json_encode($yaml, \JSON_THROW_ON_ERROR), false, flags: \JSON_THROW_ON_ERROR);
    }
}
