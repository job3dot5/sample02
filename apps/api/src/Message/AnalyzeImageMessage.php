<?php

declare(strict_types=1);

namespace App\Message;

final readonly class AnalyzeImageMessage
{
    public function __construct(
        private int $imageId,
    ) {
    }

    public function imageId(): int
    {
        return $this->imageId;
    }
}
