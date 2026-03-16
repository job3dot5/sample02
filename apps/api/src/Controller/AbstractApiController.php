<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\ProblemDetails;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

abstract class AbstractApiController extends AbstractController
{
    public function __construct(private readonly string $urnErrorPrefix)
    {
    }

    /**
     * @param array<string,mixed> $extensions
     */
    protected function problem(
        int $status,
        string $title,
        string $type,
        ?string $detail = null,
        array $extensions = [],
    ): JsonResponse {
        return ProblemDetails::response($status, $title, $type, $detail, $extensions);
    }

    protected function errorType(string $errorCode): string
    {
        return ProblemDetails::errorType($this->urnErrorPrefix, $errorCode);
    }
}
