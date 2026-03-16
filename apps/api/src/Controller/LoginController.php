<?php

declare(strict_types=1);

namespace App\Controller;

use App\Security\ApiCredentialsAuthenticator;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class LoginController extends AbstractApiController
{
    public function __construct(
        private readonly ApiCredentialsAuthenticator $credentialsAuthenticator,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
    ) {
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return $this->problem(
                JsonResponse::HTTP_BAD_REQUEST,
                'Bad Request',
                'urn:sample02:error:invalid-json',
                'Invalid JSON body.',
            );
        }

        $username = $payload['username'] ?? null;
        $password = $payload['password'] ?? null;
        if (!is_string($username) || !is_string($password)) {
            return $this->problem(
                JsonResponse::HTTP_BAD_REQUEST,
                'Bad Request',
                'urn:sample02:error:missing-credentials',
                'username and password are required.',
            );
        }

        $user = $this->credentialsAuthenticator->authenticate($username, $password);
        if (null === $user) {
            return $this->problem(
                JsonResponse::HTTP_UNAUTHORIZED,
                'Invalid credentials',
                'urn:sample02:error:invalid-credentials',
                'Username or password is incorrect.',
            );
        }

        $token = $this->jwtTokenManager->create($user);

        return new JsonResponse(['token' => $token], JsonResponse::HTTP_OK);
    }
}
