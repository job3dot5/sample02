<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\ImageController;
use App\Repository\ImageRepository;
use App\Service\ImageService;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ImageControllerTest extends TestCase
{
    private const MAX_UPLOAD_SIZE_BYTES = 5 * 1024 * 1024;

    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir().'/image_controller_test_'.bin2hex(random_bytes(8));
        mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    public function testUploadReturnsBadRequestWhenFileIsMissing(): void
    {
        $controller = $this->createController();

        $response = $controller->upload(Request::create('/images', 'POST'), $this->createImageService());

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('content-type'));
        self::assertJsonStringEqualsJsonString(
            '{"type":"urn:sample02:error:image-file-required","title":"Bad Request","status":400,"detail":"Multipart field \"file\" is required."}',
            (string) $response->getContent(),
        );
    }

    public function testUploadReturnsBadRequestWhenUploadedFileIsInvalid(): void
    {
        $controller = $this->createController();
        $path = $this->tmpDir.'/invalid.bin';
        file_put_contents($path, 'invalid');

        $uploadedFile = new UploadedFile($path, 'invalid.bin', null, \UPLOAD_ERR_CANT_WRITE, true);
        $request = $this->requestWithFile($uploadedFile);
        $response = $controller->upload($request, $this->createImageService());

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('content-type'));
        self::assertJsonStringEqualsJsonString(
            '{"type":"urn:sample02:error:image-upload-invalid","title":"Bad Request","status":400,"detail":"Uploaded image is invalid."}',
            (string) $response->getContent(),
        );
    }

    public function testUploadReturnsPayloadTooLargeWhenLimitExceeded(): void
    {
        $controller = $this->createController();
        $path = $this->tmpDir.'/too-large.bin';
        file_put_contents($path, str_repeat('A', self::MAX_UPLOAD_SIZE_BYTES + 1));

        $uploadedFile = new UploadedFile($path, 'too-large.bin', 'application/octet-stream', null, true);
        $request = $this->requestWithFile($uploadedFile);
        $response = $controller->upload($request, $this->createImageService());

        self::assertSame(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('content-type'));
        self::assertJsonStringEqualsJsonString(
            '{"type":"urn:sample02:error:image-too-large","title":"Payload Too Large","status":413,"detail":"Uploaded image exceeds the 5 MB limit."}',
            (string) $response->getContent(),
        );
    }

    public function testUploadReturnsBadRequestWhenServiceRejectsNonImage(): void
    {
        if (!class_exists(\Imagick::class)) {
            self::markTestSkipped('Imagick extension is required for ImageController image tests.');
        }

        $controller = $this->createController();
        $path = $this->tmpDir.'/not-image.txt';
        file_put_contents($path, 'not an image');

        $uploadedFile = new UploadedFile($path, 'not-image.txt', 'text/plain', null, true);
        $request = $this->requestWithFile($uploadedFile);
        $response = $controller->upload($request, $this->createImageService());

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('content-type'));
        self::assertJsonStringEqualsJsonString(
            '{"type":"urn:sample02:error:image-upload-invalid","title":"Bad Request","status":400,"detail":"Uploaded file must be an image."}',
            (string) $response->getContent(),
        );
    }

    public function testUploadReturnsCreatedForValidImage(): void
    {
        if (!class_exists(\Imagick::class)) {
            self::markTestSkipped('Imagick extension is required for ImageController image tests.');
        }

        $controller = $this->createController();
        $uploadedFile = new UploadedFile(
            $this->createJpegFixture(1200, 800),
            'fixture.jpg',
            'image/jpeg',
            null,
            true,
        );

        $response = $controller->upload($this->requestWithFile($uploadedFile), $this->createImageService());
        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('content-type'));
        self::assertIsArray($payload);
        self::assertArrayHasKey('data', $payload);
        self::assertSame('fixture.jpg', $payload['data']['original_filename']);
        self::assertSame('landscape', $payload['data']['orientation']);
    }

    public function testRenderReturnsBadRequestWhenVariantIsInvalid(): void
    {
        $controller = $this->createController();
        $repository = $this->createImageRepository();

        $response = $controller->renderImage(
            1,
            Request::create('/image/1', 'GET', ['variant' => 'invalid']),
            $repository,
        );

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('content-type'));
    }

    public function testRenderReturnsNotFoundWhenImageDoesNotExist(): void
    {
        $controller = $this->createController();
        $repository = $this->createImageRepository();

        $response = $controller->renderImage(999, Request::create('/image/999', 'GET'), $repository);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('content-type'));
    }

    public function testRenderReturnsNotFoundWhenFileIsMissingOnDisk(): void
    {
        $controller = $this->createController();
        $repository = $this->createImageRepository();

        $id = $repository->create([
            'original_filename' => 'missing.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 10,
            'width' => 100,
            'height' => 100,
            'orientation' => 'square',
            'metadata_json' => '{}',
            'original_path' => 'var/storage/images/original/2026-03/missing.jpg',
            'thumbnail_path' => 'var/storage/images/thumbnail/2026-03/missing.thumb.jpg',
            'resized_path' => 'var/storage/images/resized/2026-03/missing.resized.jpg',
        ]);

        $response = $controller->renderImage($id, Request::create(sprintf('/image/%d', $id), 'GET'), $repository);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('content-type'));
    }

    public function testRenderReturnsBinaryImageForValidId(): void
    {
        $controller = $this->createController();
        $repository = $this->createImageRepository();
        $relativePath = 'var/storage/images/original/2026-03/rendered.jpg';
        $absolutePath = $this->projectDir().'/'.$relativePath;
        $this->ensureDirectory(dirname($absolutePath));
        file_put_contents($absolutePath, 'jpeg-content');

        $id = $repository->create([
            'original_filename' => 'rendered.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 12,
            'width' => 100,
            'height' => 100,
            'orientation' => 'square',
            'metadata_json' => '{}',
            'original_path' => $relativePath,
            'thumbnail_path' => 'var/storage/images/thumbnail/2026-03/rendered.thumb.jpg',
            'resized_path' => 'var/storage/images/resized/2026-03/rendered.resized.jpg',
        ]);

        $response = $controller->renderImage($id, Request::create(sprintf('/image/%d', $id), 'GET'), $repository);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('image/jpeg', $response->headers->get('content-type'));
        self::assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));
    }

    public function testRenderSupportsLegacyStoragePathWithoutVarPrefix(): void
    {
        $controller = $this->createController();
        $repository = $this->createImageRepository();
        $legacyRelativePath = 'storage/images/original/2026-03/legacy.jpg';
        $absolutePath = $this->projectDir().'/var/'.$legacyRelativePath;
        $this->ensureDirectory(dirname($absolutePath));
        file_put_contents($absolutePath, 'jpeg-content');

        $id = $repository->create([
            'original_filename' => 'legacy.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 12,
            'width' => 100,
            'height' => 100,
            'orientation' => 'square',
            'metadata_json' => '{}',
            'original_path' => $legacyRelativePath,
            'thumbnail_path' => 'storage/images/thumbnail/2026-03/legacy.thumb.jpg',
            'resized_path' => 'storage/images/resized/2026-03/legacy.resized.jpg',
        ]);

        $response = $controller->renderImage($id, Request::create(sprintf('/image/%d', $id), 'GET'), $repository);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('image/jpeg', $response->headers->get('content-type'));
    }

    private function requestWithFile(UploadedFile $file): Request
    {
        return Request::create('/images', 'POST', [], [], ['file' => $file]);
    }

    private function createImageService(): ImageService
    {
        $repository = $this->createImageRepository();
        $storageRoot = $this->projectDir().'/var/storage/images';
        $this->ensureDirectory($storageRoot);

        return new ImageService(
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
    }

    private function createImageRepository(): ImageRepository
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        return new ImageRepository($connection);
    }

    private function createController(): ImageController
    {
        return new ImageController(self::MAX_UPLOAD_SIZE_BYTES, $this->projectDir(), 'urn:sample02:error');
    }

    private function projectDir(): string
    {
        return $this->tmpDir.'/project';
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
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
