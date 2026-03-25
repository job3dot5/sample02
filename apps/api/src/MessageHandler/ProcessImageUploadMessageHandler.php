<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\AnalyzeImageMessage;
use App\Message\ProcessImageUploadMessage;
use App\Repository\JobTrackingRepository;
use App\Service\ImageService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class ProcessImageUploadMessageHandler
{
    public function __construct(
        private ImageService $imageService,
        private MessageBusInterface $messageBus,
        private JobTrackingRepository $jobTrackingRepository,
        private LoggerInterface $imageProcessingLogger,
    ) {
    }

    public function __invoke(ProcessImageUploadMessage $message): void
    {
        $this->jobTrackingRepository->markProcessing($message->jobId());
        $this->imageProcessingLogger->info('image.worker.started', [
            'job_id' => $message->jobId(),
            'staged_path' => $message->stagedPath(),
            'original_filename' => $message->originalFilename(),
            'mime_type' => $message->mimeType(),
        ]);

        try {
            $imageId = $this->imageService->processStagedUpload(
                $message->stagedPath(),
                $message->originalFilename(),
                $message->mimeType(),
            );

            try {
                $this->messageBus->dispatch(new AnalyzeImageMessage($imageId));
            } catch (\Throwable $dispatchException) {
                $this->imageProcessingLogger->warning('image.analysis.dispatch_failed', [
                    'image_id' => $imageId,
                    'error' => $dispatchException->getMessage(),
                ]);
            }

            $this->jobTrackingRepository->markCompleted($message->jobId(), $imageId);
            $this->imageProcessingLogger->info('image.worker.succeeded', [
                'job_id' => $message->jobId(),
                'image_id' => $imageId,
                'staged_path' => $message->stagedPath(),
                'original_filename' => $message->originalFilename(),
            ]);
        } catch (\Throwable $exception) {
            $this->jobTrackingRepository->markFailed($message->jobId(), $exception->getMessage());
            $this->imageProcessingLogger->error('image.worker.failed', [
                'job_id' => $message->jobId(),
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
