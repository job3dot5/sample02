<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Http\ProblemDetails;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $throwable = $event->getThrowable();
        $status = Response::HTTP_INTERNAL_SERVER_ERROR;
        $title = 'Internal Server Error';
        $detail = 'An unexpected error occurred.';

        if ($throwable instanceof HttpExceptionInterface) {
            $status = $throwable->getStatusCode();
            $detail = '' !== $throwable->getMessage() ? $throwable->getMessage() : $detail;
            $title = match ($status) {
                Response::HTTP_BAD_REQUEST => 'Bad Request',
                Response::HTTP_UNAUTHORIZED => 'Unauthorized',
                Response::HTTP_FORBIDDEN => 'Forbidden',
                Response::HTTP_NOT_FOUND => 'Not Found',
                Response::HTTP_METHOD_NOT_ALLOWED => 'Method Not Allowed',
                default => $title,
            };
        }

        $event->setResponse(ProblemDetails::response(
            $status,
            $title,
            sprintf('urn:sample02:error:http-%d', $status),
            $detail,
        ));
    }
}
