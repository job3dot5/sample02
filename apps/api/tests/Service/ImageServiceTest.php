<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Repository\ImageRepository;
use App\Service\ImageService;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ImageServiceTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        if (!class_exists(\Imagick::class)) {
            self::markTestSkipped('Imagick extension is required for ImageService tests.');
        }

        $this->tmpDir = sys_get_temp_dir().'/image_service_test_'.bin2hex(random_bytes(8));
        mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    public function testHandleUploadCreatesFilesAndPersistsRow(): void
    {
        $uploadedPath = $this->createJpegFixture(1200, 800);
        $uploadedFile = new UploadedFile($uploadedPath, 'fixture.jpg', 'image/jpeg', null, true);

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $repository = new ImageRepository($connection);
        $storageRoot = $this->tmpDir.'/project/var/storage/images';
        mkdir($storageRoot, 0775, true);

        $service = new ImageService(
            $storageRoot,
            'original',
            'thumbnail',
            'resized',
            256,
            256,
            1280,
            1280,
            $repository,
        );

        $saved = $service->handleUpload($uploadedFile);

        self::assertSame('fixture.jpg', $saved['original_filename']);
        self::assertSame('image/jpeg', $saved['mime_type']);
        self::assertSame('landscape', $saved['orientation']);
        self::assertIsArray($saved['metadata']);
        self::assertSame('imagick', $saved['metadata']['processor']);
        self::assertStringContainsString('/original/', $saved['original_path']);
        self::assertStringContainsString('/thumbnail/', $saved['thumbnail_path']);
        self::assertStringContainsString('/resized/', $saved['resized_path']);

        $projectRoot = dirname($storageRoot, 3);
        self::assertFileExists($projectRoot.'/'.$saved['original_path']);
        self::assertFileExists($projectRoot.'/'.$saved['thumbnail_path']);
        self::assertFileExists($projectRoot.'/'.$saved['resized_path']);
    }

    public function testHandleUploadRejectsNonImageFile(): void
    {
        $txtPath = $this->tmpDir.'/fixture.txt';
        file_put_contents($txtPath, 'not an image');
        $uploadedFile = new UploadedFile($txtPath, 'fixture.txt', 'text/plain', null, true);

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $repository = new ImageRepository($connection);
        $storageRoot = $this->tmpDir.'/project/var/storage/images';
        mkdir($storageRoot, 0775, true);

        $service = new ImageService(
            $storageRoot,
            'original',
            'thumbnail',
            'resized',
            256,
            256,
            1280,
            1280,
            $repository,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Uploaded file must be an image.');

        $service->handleUpload($uploadedFile);
    }

    public function testHandleUploadDoesNotUpscaleSmallImages(): void
    {
        $uploadedPath = $this->createJpegFixture(640, 480);
        $uploadedFile = new UploadedFile($uploadedPath, 'small.jpg', 'image/jpeg', null, true);

        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $repository = new ImageRepository($connection);
        $storageRoot = $this->tmpDir.'/project/var/storage/images';
        mkdir($storageRoot, 0775, true);

        $service = new ImageService(
            $storageRoot,
            'original',
            'thumbnail',
            'resized',
            256,
            256,
            1280,
            1280,
            $repository,
        );

        $saved = $service->handleUpload($uploadedFile);
        $projectRoot = dirname($storageRoot, 3);
        $resizedPath = $projectRoot.'/'.$saved['resized_path'];

        $dimensions = getimagesize($resizedPath);
        self::assertIsArray($dimensions);
        self::assertSame(640, (int) $dimensions[0]);
        self::assertSame(480, (int) $dimensions[1]);
    }

    private function createJpegFixture(int $width, int $height): string
    {
        $path = $this->tmpDir.'/fixture.jpg';
        $image = new \Imagick();
        $image->newImage($width, $height, new \ImagickPixel('#228be6'));
        $image->setImageFormat('jpeg');
        $image->writeImage($path);
        $image->clear();
        $image->destroy();

        return $path;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
