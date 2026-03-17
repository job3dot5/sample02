<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Repository\ImageRepository;
use App\Service\ImageService;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

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

    public function testProcessStagedUploadCreatesFilesAndPersistsRow(): void
    {
        $stagedPath = $this->tmpDir.'/staged.fixture.jpg';
        copy($this->createJpegFixture(1200, 800), $stagedPath);

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
            new NullLogger(),
        );

        $service->processStagedUpload($stagedPath, 'fixture.jpg', 'image/jpeg');
        self::assertFileDoesNotExist($stagedPath);
        $saved = $repository->find(1);
        self::assertIsArray($saved);
        $saved['metadata'] = json_decode((string) $saved['metadata_json'], true);

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

    public function testProcessStagedUploadRejectsNonImageFile(): void
    {
        $stagedPath = $this->tmpDir.'/staged.fixture.txt';
        file_put_contents($stagedPath, 'not an image');

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
            new NullLogger(),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Uploaded file must be an image.');

        $service->processStagedUpload($stagedPath, 'fixture.txt', 'text/plain');
    }

    public function testProcessStagedUploadDoesNotUpscaleSmallImages(): void
    {
        $stagedPath = $this->tmpDir.'/staged.small.jpg';
        copy($this->createJpegFixture(640, 480), $stagedPath);

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
            new NullLogger(),
        );

        $service->processStagedUpload($stagedPath, 'small.jpg', 'image/jpeg');
        $saved = $repository->find(1);
        self::assertIsArray($saved);
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
