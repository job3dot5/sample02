<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class MeController extends AbstractApiController
{
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function __invoke(Security $security): JsonResponse
    {
        $user = $security->getUser();
        if (!$user instanceof UserInterface) {
            return $this->problem(
                JsonResponse::HTTP_UNAUTHORIZED,
                'Unauthorized',
                'urn:sample02:error:unauthorized',
                'Authentication is required.',
            );
        }

        return new JsonResponse([
            'user' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
        ]);
    }
}
