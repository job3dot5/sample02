<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Http\ProblemDetails;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTNotFoundEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;

final readonly class JwtFailureSubscriber implements EventSubscriberInterface
{
    public function __construct(private string $urnErrorPrefix)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::JWT_NOT_FOUND => 'onJwtNotFound',
            Events::JWT_INVALID => 'onJwtInvalid',
            Events::JWT_EXPIRED => 'onJwtExpired',
        ];
    }

    public function onJwtNotFound(JWTNotFoundEvent $event): void
    {
        $event->setResponse(ProblemDetails::response(
            Response::HTTP_UNAUTHORIZED,
            'Missing token',
            ProblemDetails::errorType($this->urnErrorPrefix, 'token-missing'),
            'Bearer token not found in Authorization header.',
        ));
    }

    public function onJwtInvalid(JWTInvalidEvent $event): void
    {
        $event->setResponse(ProblemDetails::response(
            Response::HTTP_UNAUTHORIZED,
            'Invalid token',
            ProblemDetails::errorType($this->urnErrorPrefix, 'token-invalid'),
            'Bearer token is invalid.',
        ));
    }

    public function onJwtExpired(JWTExpiredEvent $event): void
    {
        $event->setResponse(ProblemDetails::response(
            Response::HTTP_UNAUTHORIZED,
            'Expired token',
            ProblemDetails::errorType($this->urnErrorPrefix, 'token-expired'),
            'Bearer token has expired.',
        ));
    }
}
