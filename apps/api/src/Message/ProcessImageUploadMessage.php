<?php

declare(strict_types=1);

namespace App\Message;

final readonly class ProcessImageUploadMessage
{
    public function __construct(
        private string $stagedPath,
        private string $originalFilename,
        private string $mimeType,
    ) {
    }

    public function stagedPath(): string
    {
        return $this->stagedPath;
    }

    public function originalFilename(): string
    {
        return $this->originalFilename;
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }
}
