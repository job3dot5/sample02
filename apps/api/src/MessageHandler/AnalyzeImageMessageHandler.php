<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\AnalyzeImageMessage;
use App\Service\ImageAnalysis\ImageAnalysisService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
final readonly class AnalyzeImageMessageHandler
{
    public function __construct(
        private ImageAnalysisService $imageAnalysisService,
        private LoggerInterface $imageProcessingLogger,
    ) {
    }

    public function __invoke(AnalyzeImageMessage $message): void
    {
        $this->imageProcessingLogger->info('image.analysis.worker.started', [
            'image_id' => $message->imageId(),
        ]);

        try {
            $this->imageAnalysisService->analyzeImage($message->imageId());
            $this->imageProcessingLogger->info('image.analysis.worker.succeeded', [
                'image_id' => $message->imageId(),
            ]);
        } catch (\Throwable $exception) {
            $this->imageProcessingLogger->error('image.analysis.worker.failed', [
                'image_id' => $message->imageId(),
                'error' => $exception->getMessage(),
            ]);

            if ($exception instanceof \InvalidArgumentException) {
                throw new UnrecoverableMessageHandlingException($exception->getMessage(), previous: $exception);
            }

            throw $exception;
        }
    }
}
