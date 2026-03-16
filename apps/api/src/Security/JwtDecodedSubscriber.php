<?php

declare(strict_types=1);

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class JwtDecodedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private string $jwtIssuer,
        private string $jwtAudience,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::JWT_DECODED => 'onJwtDecoded',
        ];
    }

    public function onJwtDecoded(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();

        $issuer = $payload['iss'] ?? null;
        $audience = $payload['aud'] ?? null;

        if (!is_string($issuer) || $issuer !== $this->jwtIssuer) {
            $event->markAsInvalid();

            return;
        }

        if (is_string($audience)) {
            $isValidAudience = $audience === $this->jwtAudience;
        } elseif (is_array($audience)) {
            $isValidAudience = in_array($this->jwtAudience, $audience, true);
        } else {
            $isValidAudience = false;
        }

        if (!$isValidAudience) {
            $event->markAsInvalid();
        }
    }
}
