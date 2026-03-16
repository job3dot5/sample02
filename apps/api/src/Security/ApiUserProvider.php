<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<UserInterface>
 */
final class ApiUserProvider implements UserProviderInterface
{
    public function __construct(private readonly ApiCredentialsAuthenticator $credentialsAuthenticator)
    {
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$this->supportsClass($user::class)) {
            throw new UnsupportedUserException(sprintf('Unsupported user class "%s".', $user::class));
        }

        $refreshedUser = $this->credentialsAuthenticator->getUserByIdentifier($user->getUserIdentifier());
        if (null === $refreshedUser) {
            throw new UserNotFoundException(sprintf('User "%s" not found.', $user->getUserIdentifier()));
        }

        return $refreshedUser;
    }

    public function supportsClass(string $class): bool
    {
        return is_a($class, UserInterface::class, true);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->credentialsAuthenticator->getUserByIdentifier($identifier);
        if (null === $user) {
            throw new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
        }

        return $user;
    }
}
