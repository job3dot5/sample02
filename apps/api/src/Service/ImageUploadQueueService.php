<?php

declare(strict_types=1);

namespace App\Service;

use App\Message\ProcessImageUploadMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ImageUploadQueueService
{
    public function __construct(
        private string $storageRoot,
        private string $pendingDirectory,
        private MessageBusInterface $messageBus,
        private LoggerInterface $imageProcessingLogger,
    ) {
    }

    public function queueUpload(UploadedFile $file): string
    {
        $mimeType = $this->validateImageMimeType($file->getMimeType() ?? '');
        $pendingRoot = $this->storageRoot.'/'.$this->pendingDirectory;
        $this->ensureDirectory($pendingRoot);

        $extension = $file->guessExtension() ?: pathinfo($file->getClientOriginalName(), \PATHINFO_EXTENSION);
        $extension = '' !== $extension ? strtolower($extension) : 'bin';

        $jobId = bin2hex(random_bytes(16));
        $stagedName = sprintf('%s.%s', $jobId, $extension);
        $stagedFile = $file->move($pendingRoot, $stagedName);
        $this->imageProcessingLogger->info('image.queue.staged', [
            'job_id' => $jobId,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $mimeType,
            'staged_path' => $stagedFile->getPathname(),
        ]);

        try {
            $this->messageBus->dispatch(new ProcessImageUploadMessage(
                $stagedFile->getPathname(),
                $file->getClientOriginalName(),
                $mimeType,
            ));
            $this->imageProcessingLogger->info('image.queue.dispatched', [
                'job_id' => $jobId,
                'staged_path' => $stagedFile->getPathname(),
            ]);
        } catch (\Throwable $exception) {
            @unlink($stagedFile->getPathname());
            $this->imageProcessingLogger->error('image.queue.dispatch_failed', [
                'job_id' => $jobId,
                'staged_path' => $stagedFile->getPathname(),
                'error' => $exception->getMessage(),
            ]);
            throw new \RuntimeException('Unable to queue image upload for asynchronous processing.', 0, $exception);
        }

        return $jobId;
    }

    private function validateImageMimeType(string $mimeType): string
    {
        if (!str_starts_with($mimeType, 'image/')) {
            throw new \InvalidArgumentException('Uploaded file must be an image.');
        }

        return $mimeType;
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
    }
}
