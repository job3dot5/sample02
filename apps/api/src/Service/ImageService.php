<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ImageRepository;
use Psr\Log\LoggerInterface;

final readonly class ImageService
{
    public function __construct(
        private string $storageRoot,
        private string $originalDirectory,
        private string $thumbnailDirectory,
        private string $resizedDirectory,
        private int $thumbnailMaxWidth,
        private int $thumbnailMaxHeight,
        private int $resizedMaxWidth,
        private int $resizedMaxHeight,
        private ImageRepository $imageRepository,
        private LoggerInterface $imageProcessingLogger,
    ) {
    }

    public function processStagedUpload(string $stagedPath, string $originalFilename, string $mimeType): void
    {
        $this->ensureImagickIsAvailable();
        $mimeType = $this->validateImageMimeType($mimeType);

        if (!is_file($stagedPath)) {
            throw new \InvalidArgumentException(sprintf('Staged upload "%s" was not found.', $stagedPath));
        }
        $this->imageProcessingLogger->info('image.processing.started', [
            'staged_path' => $stagedPath,
            'original_filename' => $originalFilename,
            'mime_type' => $mimeType,
        ]);

        $partition = (new \DateTimeImmutable())->format('Y-m');
        $this->ensureStorageDirectories($partition);

        $extension = pathinfo($stagedPath, \PATHINFO_EXTENSION);
        $extension = '' !== $extension ? strtolower($extension) : 'bin';
        $basename = bin2hex(random_bytes(12));

        $originalPath = sprintf('%s/%s.%s', $this->originalDir($partition), $basename, $extension);
        $thumbnailPath = sprintf('%s/%s.thumb.%s', $this->thumbnailDir($partition), $basename, $extension);
        $resizedPath = sprintf('%s/%s.resized.%s', $this->resizedDir($partition), $basename, $extension);

        if (!copy($stagedPath, $originalPath)) {
            throw new \RuntimeException('Unable to copy staged upload into original image storage.');
        }
        $this->assertFileExists($originalPath, 'original image');
        $this->imageProcessingLogger->info('image.processing.copied_to_original', [
            'staged_path' => $stagedPath,
            'original_path' => $originalPath,
        ]);

        try {
            $imageId = $this->processStoredOriginal(
                $originalPath,
                $thumbnailPath,
                $resizedPath,
                $originalFilename,
                $mimeType,
            );
            $this->imageProcessingLogger->info('image.processing.persisted', [
                'image_id' => $imageId,
                'original_path' => $originalPath,
                'thumbnail_path' => $thumbnailPath,
                'resized_path' => $resizedPath,
            ]);
        } catch (\Throwable $exception) {
            $this->cleanupFiles([$originalPath, $thumbnailPath, $resizedPath]);
            $this->imageProcessingLogger->warning('image.processing.cleanup_after_failure', [
                'staged_path' => $stagedPath,
                'original_path' => $originalPath,
                'thumbnail_path' => $thumbnailPath,
                'resized_path' => $resizedPath,
                'error' => $exception->getMessage(),
            ]);
            throw $exception;
        }

        @unlink($stagedPath);
        $this->imageProcessingLogger->info('image.processing.completed', [
            'image_id' => $imageId,
            'staged_path' => $stagedPath,
            'original_filename' => $originalFilename,
        ]);
    }

    private function processStoredOriginal(
        string $originalPath,
        string $thumbnailPath,
        string $resizedPath,
        string $originalFilename,
        string $mimeType,
    ): int {
        [$width, $height] = $this->readDimensions($originalPath);
        $orientation = $this->detectOrientation($width, $height);

        $this->generateThumbnail($originalPath, $thumbnailPath);
        $this->generateResized($originalPath, $resizedPath, $width, $height);
        $this->assertFileExists($thumbnailPath, 'thumbnail image');
        $this->assertFileExists($resizedPath, 'resized image');

        $metadata = [
            'mimeType' => $mimeType,
            'sizeBytes' => filesize($originalPath),
            'width' => $width,
            'height' => $height,
            'orientation' => $orientation,
            'processor' => 'imagick',
        ];

        try {
            return $this->imageRepository->create([
                'original_filename' => $originalFilename,
                'mime_type' => $mimeType,
                'size_bytes' => (int) $metadata['sizeBytes'],
                'width' => $width,
                'height' => $height,
                'orientation' => $orientation,
                'metadata_json' => json_encode($metadata, \JSON_THROW_ON_ERROR),
                'original_path' => $this->relativePath($originalPath),
                'thumbnail_path' => $this->relativePath($thumbnailPath),
                'resized_path' => $this->relativePath($resizedPath),
            ]);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Unable to persist image metadata row.', 0, $exception);
        }
    }

    private function ensureImagickIsAvailable(): void
    {
        if (!class_exists(\Imagick::class)) {
            throw new \RuntimeException('Imagick extension is required for image processing.');
        }
    }

    private function validateImageMimeType(string $mimeType): string
    {
        if (!str_starts_with($mimeType, 'image/')) {
            throw new \InvalidArgumentException('Uploaded file must be an image.');
        }

        return $mimeType;
    }

    private function generateThumbnail(string $sourcePath, string $targetPath): void
    {
        $image = new \Imagick($sourcePath);
        $image->thumbnailImage($this->thumbnailMaxWidth, $this->thumbnailMaxHeight, true, true);
        $image->writeImage($targetPath);
        $image->clear();
    }

    private function generateResized(string $sourcePath, string $targetPath, int $sourceWidth, int $sourceHeight): void
    {
        if ($sourceWidth <= $this->resizedMaxWidth && $sourceHeight <= $this->resizedMaxHeight) {
            if (!copy($sourcePath, $targetPath)) {
                throw new \RuntimeException('Unable to write resized image file.');
            }

            return;
        }

        $image = new \Imagick($sourcePath);
        $image->resizeImage($this->resizedMaxWidth, $this->resizedMaxHeight, \Imagick::FILTER_LANCZOS, 1, true);
        $image->writeImage($targetPath);
        $image->clear();
    }

    /**
     * @return array{int,int}
     */
    private function readDimensions(string $path): array
    {
        $dimensions = getimagesize($path);
        if (false === $dimensions) {
            throw new \InvalidArgumentException('Unable to read image dimensions.');
        }

        return [(int) $dimensions[0], (int) $dimensions[1]];
    }

    private function detectOrientation(int $width, int $height): string
    {
        if ($width === $height) {
            return 'square';
        }

        return $width > $height ? 'landscape' : 'portrait';
    }

    private function ensureStorageDirectories(string $partition): void
    {
        foreach ([$this->originalDir($partition), $this->thumbnailDir($partition), $this->resizedDir($partition)] as $directory) {
            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }
        }
    }

    private function originalDir(string $partition): string
    {
        return $this->storageRoot.'/'.$this->originalDirectory.'/'.$partition;
    }

    private function thumbnailDir(string $partition): string
    {
        return $this->storageRoot.'/'.$this->thumbnailDirectory.'/'.$partition;
    }

    private function resizedDir(string $partition): string
    {
        return $this->storageRoot.'/'.$this->resizedDirectory.'/'.$partition;
    }

    private function relativePath(string $absolutePath): string
    {
        $projectDir = dirname($this->storageRoot, 3);

        return ltrim(str_replace($projectDir, '', $absolutePath), '/');
    }

    private function assertFileExists(string $path, string $label): void
    {
        if (!is_file($path)) {
            throw new \RuntimeException(sprintf('Generated %s file was not found at "%s".', $label, $path));
        }
    }

    /**
     * @param list<string> $paths
     */
    private function cleanupFiles(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
