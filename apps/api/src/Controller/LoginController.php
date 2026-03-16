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
        string $urnErrorPrefix,
    ) {
        parent::__construct($urnErrorPrefix);
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return $this->problem(
                JsonResponse::HTTP_BAD_REQUEST,
                'Bad Request',
                $this->errorType('invalid-json'),
                'Invalid JSON body.',
            );
        }

        $username = $payload['username'] ?? null;
        $password = $payload['password'] ?? null;
        if (!is_string($username) || !is_string($password)) {
            return $this->problem(
                JsonResponse::HTTP_BAD_REQUEST,
                'Bad Request',
                $this->errorType('missing-credentials'),
                'username and password are required.',
            );
        }

        $user = $this->credentialsAuthenticator->authenticate($username, $password);
        if (null === $user) {
            return $this->problem(
                JsonResponse::HTTP_UNAUTHORIZED,
                'Invalid credentials',
                $this->errorType('invalid-credentials'),
                'Username or password is incorrect.',
            );
        }

        $token = $this->jwtTokenManager->create($user);

        return new JsonResponse(['token' => $token], JsonResponse::HTTP_OK);
    }
}
