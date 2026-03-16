<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

final class ApiCredentialsAuthenticator
{
    public function __construct(
        private readonly string $apiUsername,
        private readonly string $apiPassword,
    ) {
    }

    public function authenticate(string $username, string $password): ?UserInterface
    {
        if (!hash_equals($this->apiUsername, $username) || !hash_equals($this->apiPassword, $password)) {
            return null;
        }

        return $this->createUser();
    }

    public function getUserByIdentifier(string $identifier): ?UserInterface
    {
        if (!hash_equals($this->apiUsername, $identifier)) {
            return null;
        }

        return $this->createUser();
    }

    private function createUser(): InMemoryUser
    {
        return new InMemoryUser($this->apiUsername, null, ['ROLE_USER']);
    }
}
