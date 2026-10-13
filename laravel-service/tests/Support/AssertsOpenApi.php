<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Testing\TestResponse;
use Osteel\OpenApi\Testing\ValidatorBuilder;
use Osteel\OpenApi\Testing\ValidatorInterface;
use Symfony\Component\HttpFoundation\Response;

trait AssertsOpenApi
{
    private ?ValidatorInterface $openApiValidator = null;

    /**
     * @param  TestResponse<Response>  $response
     */
    protected function assertMatchesOpenApi(TestResponse $response, string $path): void
    {
        $this->openApiValidator ??= ValidatorBuilder::fromYamlFile(public_path('openapi/openapi.yaml'))->getValidator();

        $this->assertTrue($this->openApiValidator->validate($response->baseResponse, $path, 'get'));
    }
}
