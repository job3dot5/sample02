<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ImageRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;

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
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function handleUpload(UploadedFile $file): array
    {
        if (!class_exists(\Imagick::class)) {
            throw new \RuntimeException('Imagick extension is required for image processing.');
        }

        $mimeType = $file->getMimeType() ?? '';
        if (!str_starts_with($mimeType, 'image/')) {
            throw new \InvalidArgumentException('Uploaded file must be an image.');
        }

        $partition = (new \DateTimeImmutable())->format('Y-m');
        $this->ensureStorageDirectories($partition);

        $extension = $file->guessExtension() ?: 'bin';
        $basename = bin2hex(random_bytes(12));

        $originalName = sprintf('%s.%s', $basename, $extension);
        $originalFile = $file->move($this->originalDir($partition), $originalName);
        $originalPath = $originalFile->getPathname();

        [$width, $height] = $this->readDimensions($originalPath);
        $orientation = $this->detectOrientation($width, $height);

        $thumbnailName = sprintf('%s.thumb.%s', $basename, $extension);
        $resizedName = sprintf('%s.resized.%s', $basename, $extension);
        $thumbnailPath = $this->thumbnailDir($partition).'/'.$thumbnailName;
        $resizedPath = $this->resizedDir($partition).'/'.$resizedName;

        $this->generateThumbnail($originalPath, $thumbnailPath);
        $this->generateResized($originalPath, $resizedPath, $width, $height);

        $metadata = [
            'mimeType' => $mimeType,
            'sizeBytes' => filesize($originalPath),
            'width' => $width,
            'height' => $height,
            'orientation' => $orientation,
            'processor' => 'imagick',
        ];

        $id = $this->imageRepository->create([
            'original_filename' => $file->getClientOriginalName(),
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

        $saved = $this->imageRepository->find($id);
        if (null === $saved) {
            throw new \RuntimeException('Image row was not found after insert.');
        }

        $saved['metadata'] = json_decode((string) $saved['metadata_json'], true);

        return $saved;
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
}
