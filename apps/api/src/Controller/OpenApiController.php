<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OpenApiController
{
    public function __construct(private readonly string $openApiSpecPath)
    {
    }

    #[Route('/docs/openapi.yaml', name: 'openapi_spec', methods: ['GET'])]
    public function spec(): Response
    {
        if (!is_file($this->openApiSpecPath)) {
            return new Response('OpenAPI specification not found.', Response::HTTP_NOT_FOUND);
        }

        $content = file_get_contents($this->openApiSpecPath);
        if (false === $content) {
            return new Response('Cannot read OpenAPI specification.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new Response($content, Response::HTTP_OK, ['Content-Type' => 'application/yaml']);
    }
}
