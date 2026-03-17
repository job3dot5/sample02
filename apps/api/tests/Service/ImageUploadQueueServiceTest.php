<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Message\ProcessImageUploadMessage;
use App\Service\ImageUploadQueueService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;

final class ImageUploadQueueServiceTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir().'/image_upload_queue_test_'.bin2hex(random_bytes(8));
        mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    public function testQueueUploadStagesFileAndDispatchesMessage(): void
    {
        $bus = new InMemoryMessageBus();
        $service = new ImageUploadQueueService($this->tmpDir.'/storage/images', 'pending', $bus, new NullLogger());

        $uploadedFile = new UploadedFile(
            $this->createTinyPngFixture(),
            'tiny.png',
            'image/png',
            null,
            true,
        );

        $jobId = $service->queueUpload($uploadedFile);

        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $jobId);
        self::assertCount(1, $bus->messages);
        self::assertInstanceOf(ProcessImageUploadMessage::class, $bus->messages[0]);

        /** @var ProcessImageUploadMessage $message */
        $message = $bus->messages[0];
        self::assertSame('tiny.png', $message->originalFilename());
        self::assertSame('image/png', $message->mimeType());
        self::assertFileExists($message->stagedPath());
        self::assertStringContainsString('/storage/images/pending/', $message->stagedPath());
    }

    public function testQueueUploadRejectsNonImage(): void
    {
        $bus = new InMemoryMessageBus();
        $service = new ImageUploadQueueService($this->tmpDir.'/storage/images', 'pending', $bus, new NullLogger());

        $txtPath = $this->tmpDir.'/not-image.txt';
        file_put_contents($txtPath, 'not an image');
        $uploadedFile = new UploadedFile($txtPath, 'not-image.txt', 'text/plain', null, true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Uploaded file must be an image.');

        $service->queueUpload($uploadedFile);
    }

    public function testQueueUploadDeletesStagedFileWhenDispatchFails(): void
    {
        $service = new ImageUploadQueueService(
            $this->tmpDir.'/storage/images',
            'pending',
            new ThrowingMessageBus(),
            new NullLogger(),
        );

        $uploadedFile = new UploadedFile(
            $this->createTinyPngFixture(),
            'tiny.png',
            'image/png',
            null,
            true,
        );

        try {
            $service->queueUpload($uploadedFile);
            self::fail('Expected runtime exception was not thrown.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Unable to queue image upload for asynchronous processing.', $exception->getMessage());
            self::assertInstanceOf(\RuntimeException::class, $exception->getPrevious());
            self::assertSame('Simulated bus failure.', $exception->getPrevious()->getMessage());
        }

        $pendingDir = $this->tmpDir.'/storage/images/pending';
        self::assertDirectoryExists($pendingDir);
        $remainingFiles = array_values(array_filter(scandir($pendingDir) ?: [], static fn (string $item): bool => !in_array($item, ['.', '..'], true)));
        self::assertCount(0, $remainingFiles);
    }

    private function createTinyPngFixture(): string
    {
        $path = $this->tmpDir.'/tiny.png';
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII=', true));

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

final class ThrowingMessageBus implements MessageBusInterface
{
    /**
     * @param array<StampInterface> $stamps
     */
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        throw new \RuntimeException('Simulated bus failure.');
    }
}
