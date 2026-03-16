<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class RootController
{
    public function __construct(private string $apiPrefix)
    {
    }

    #[Route('/', name: 'root_redirect', methods: ['GET'])]
    public function __invoke(): RedirectResponse
    {
        return new RedirectResponse($this->apiPrefix.'/health');
    }
}
