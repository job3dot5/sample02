<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\ImageController;
use App\Repository\ImageRepository;
use App\Service\ImageUploadQueueService;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;

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

        $response = $controller->upload(Request::create('/images', 'POST'), $this->createImageUploadQueueService());

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
        $response = $controller->upload($request, $this->createImageUploadQueueService());

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
        $response = $controller->upload($request, $this->createImageUploadQueueService());

        self::assertSame(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('content-type'));
        self::assertJsonStringEqualsJsonString(
            '{"type":"urn:sample02:error:image-too-large","title":"Payload Too Large","status":413,"detail":"Uploaded image exceeds the 5 MB limit."}',
            (string) $response->getContent(),
        );
    }

    public function testUploadReturnsBadRequestWhenQueuedFileIsNotAnImage(): void
    {
        $controller = $this->createController();
        $path = $this->tmpDir.'/not-image.txt';
        file_put_contents($path, 'not an image');

        $uploadedFile = new UploadedFile($path, 'not-image.txt', 'text/plain', null, true);
        $request = $this->requestWithFile($uploadedFile);
        $response = $controller->upload($request, $this->createImageUploadQueueService());

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('content-type'));
        self::assertJsonStringEqualsJsonString(
            '{"type":"urn:sample02:error:image-upload-invalid","title":"Bad Request","status":400,"detail":"Uploaded file must be an image."}',
            (string) $response->getContent(),
        );
    }

    public function testUploadReturnsAcceptedForValidImage(): void
    {
        if (!class_exists(\Imagick::class)) {
            self::markTestSkipped('Imagick extension is required for ImageController image tests.');
        }

        $controller = $this->createController();
        $messageBus = new InMemoryMessageBus();
        $uploadedFile = new UploadedFile(
            $this->createJpegFixture(1200, 800),
            'fixture.jpg',
            'image/jpeg',
            null,
            true,
        );

        $response = $controller->upload(
            $this->requestWithFile($uploadedFile),
            $this->createImageUploadQueueService($messageBus),
        );
        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('content-type'));
        self::assertIsArray($payload);
        self::assertArrayHasKey('data', $payload);
        self::assertSame('queued', $payload['data']['status']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', (string) $payload['data']['job_id']);
        self::assertCount(1, $messageBus->messages);
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

    public function testListReturnsPaginatedImages(): void
    {
        $controller = $this->createController();
        $repository = $this->createImageRepository();

        $repository->create([
            'original_filename' => 'first.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 12,
            'width' => 100,
            'height' => 100,
            'orientation' => 'square',
            'metadata_json' => '{}',
            'original_path' => 'var/storage/images/original/2026-03/first.jpg',
            'thumbnail_path' => 'var/storage/images/thumbnail/2026-03/first.thumb.jpg',
            'resized_path' => 'var/storage/images/resized/2026-03/first.resized.jpg',
        ]);
        $secondId = $repository->create([
            'original_filename' => 'second.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 13,
            'width' => 200,
            'height' => 100,
            'orientation' => 'landscape',
            'metadata_json' => '{}',
            'original_path' => 'var/storage/images/original/2026-03/second.jpg',
            'thumbnail_path' => 'var/storage/images/thumbnail/2026-03/second.thumb.jpg',
            'resized_path' => 'var/storage/images/resized/2026-03/second.resized.jpg',
        ]);

        $response = $controller->list(Request::create('/images', 'GET', ['page' => '1', 'per_page' => '1']), $repository);
        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('content-type'));
        self::assertSame(1, $payload['meta']['page']);
        self::assertSame(1, $payload['meta']['per_page']);
        self::assertSame(2, $payload['meta']['total']);
        self::assertSame(2, $payload['meta']['total_pages']);
        self::assertCount(1, $payload['data']);
        self::assertSame($secondId, $payload['data'][0]['id']);
        self::assertSame('second.jpg', $payload['data'][0]['original_filename']);
        self::assertArrayNotHasKey('original_path', $payload['data'][0]);
        self::assertArrayNotHasKey('thumbnail_path', $payload['data'][0]);
        self::assertArrayNotHasKey('resized_path', $payload['data'][0]);
        self::assertSame(sprintf('/api/v1/image/%d?variant=resized', $secondId), $payload['data'][0]['image_urls']['resized']);
    }

    public function testListReturnsBadRequestWhenPageIsInvalid(): void
    {
        $controller = $this->createController();
        $repository = $this->createImageRepository();

        $response = $controller->list(Request::create('/images', 'GET', ['page' => '0']), $repository);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('content-type'));
    }

    public function testListReturnsBadRequestWhenPerPageIsInvalid(): void
    {
        $controller = $this->createController();
        $repository = $this->createImageRepository();

        $response = $controller->list(Request::create('/images', 'GET', ['per_page' => '0']), $repository);
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('content-type'));

        $response = $controller->list(Request::create('/images', 'GET', ['per_page' => '101']), $repository);
        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->headers->get('content-type'));
    }

    private function requestWithFile(UploadedFile $file): Request
    {
        return Request::create('/images', 'POST', [], [], ['file' => $file]);
    }

    private function createImageUploadQueueService(?MessageBusInterface $messageBus = null): ImageUploadQueueService
    {
        $storageRoot = $this->projectDir().'/var/storage/images';
        $this->ensureDirectory($storageRoot);
        $messageBus ??= new InMemoryMessageBus();

        return new ImageUploadQueueService(
            $storageRoot,
            'pending',
            $messageBus,
            new NullLogger(),
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

final class InMemoryMessageBus implements MessageBusInterface
{
    /** @var list<object> */
    public array $messages = [];

    /**
     * @param array<StampInterface> $stamps
     */
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->messages[] = $message;

        return new Envelope($message, $stamps);
    }
}
