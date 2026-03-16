<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

final class RouteTest extends KernelTestCase
{
    public function testRootRedirectsToHealthRoute(): void
    {
        $response = $this->request('GET', '/');

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        self::assertSame('/api/v1/health', $response->headers->get('Location'));
    }

    public function testHealthRouteReturnsOk(): void
    {
        $response = $this->request('GET', '/api/v1/health');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('content-type'));
        self::assertJsonStringEqualsJsonString(
            '{"status":"ok","service":"api"}',
            (string) $response->getContent(),
        );
    }

    public function testLoginRouteReturnsBadRequestOnInvalidJson(): void
    {
        $response = $this->request('POST', '/api/v1/login', '{', ['CONTENT_TYPE' => 'application/json']);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('content-type'));
    }

    public function testMeRouteReturnsUnauthorizedWithoutToken(): void
    {
        $response = $this->request('GET', '/api/v1/me');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('content-type'));
    }

    public function testImageUploadRouteReturnsUnauthorizedWithoutToken(): void
    {
        $response = $this->request('POST', '/api/v1/images');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('content-type'));
    }

    public function testImageRenderRouteReturnsUnauthorizedWithoutToken(): void
    {
        $response = $this->request('GET', '/api/v1/image/1');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('content-type'));
    }

    public function testOpenApiRouteReturnsSpec(): void
    {
        $response = $this->request('GET', '/docs/openapi.v1.yaml');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('application/yaml', $response->headers->get('content-type'));
        self::assertStringContainsString('openapi: 3.1.0', (string) $response->getContent());
    }

    /**
     * @param array<string,string> $server
     */
    private function request(string $method, string $uri, ?string $content = null, array $server = []): Response
    {
        self::bootKernel();
        $kernel = self::$kernel;
        self::assertInstanceOf(KernelInterface::class, $kernel);

        $request = Request::create($uri, $method, [], [], [], $server, $content);
        $response = $kernel->handle($request);
        if ($kernel instanceof TerminableInterface) {
            $kernel->terminate($request, $response);
        }
        self::ensureKernelShutdown();

        return $response;
    }
}
