<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;

final class ProblemDetails
{
    /**
     * @param array<string,mixed> $extensions
     */
    public static function response(
        int $status,
        string $title,
        string $type,
        ?string $detail = null,
        array $extensions = [],
    ): JsonResponse {
        $payload = [
            'type' => $type,
            'title' => $title,
            'status' => $status,
        ];

        if (null !== $detail && '' !== $detail) {
            $payload['detail'] = $detail;
        }

        foreach ($extensions as $key => $value) {
            $payload[$key] = $value;
        }

        return new JsonResponse(
            $payload,
            $status,
            ['Content-Type' => 'application/problem+json'],
        );
    }
}
