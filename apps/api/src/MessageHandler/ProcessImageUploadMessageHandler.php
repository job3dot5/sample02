<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ProcessImageUploadMessage;
use App\Service\ImageService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
final readonly class ProcessImageUploadMessageHandler
{
    public function __construct(
        private ImageService $imageService,
        private LoggerInterface $imageProcessingLogger,
    ) {
    }

    public function __invoke(ProcessImageUploadMessage $message): void
    {
        $this->imageProcessingLogger->info('image.worker.started', [
            'staged_path' => $message->stagedPath(),
            'original_filename' => $message->originalFilename(),
            'mime_type' => $message->mimeType(),
        ]);

        try {
            $this->imageService->processStagedUpload(
                $message->stagedPath(),
                $message->originalFilename(),
                $message->mimeType(),
            );
            $this->imageProcessingLogger->info('image.worker.succeeded', [
                'staged_path' => $message->stagedPath(),
                'original_filename' => $message->originalFilename(),
            ]);
        } catch (\Throwable $exception) {
            $this->imageProcessingLogger->error('image.worker.failed', [
                'staged_path' => $message->stagedPath(),
                'original_filename' => $message->originalFilename(),
                'error' => $exception->getMessage(),
            ]);

            if ($exception instanceof \ImagickException || $exception instanceof \InvalidArgumentException) {
                @unlink($message->stagedPath());

                throw new UnrecoverableMessageHandlingException($exception->getMessage(), previous: $exception);
            }

            throw $exception;
        }
    }
}
