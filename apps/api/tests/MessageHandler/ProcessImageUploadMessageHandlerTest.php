<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Message\AnalyzeImageMessage;
use App\Message\ProcessImageUploadMessage;
use App\MessageHandler\ProcessImageUploadMessageHandler;
use App\Repository\ImageRepository;
use App\Repository\JobTrackingRepository;
use App\Service\ImageService;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;

final class ProcessImageUploadMessageHandlerTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        if (!class_exists(\Imagick::class)) {
            self::markTestSkipped('Imagick extension is required for ProcessImageUploadMessageHandler tests.');
        }

        $this->tmpDir = sys_get_temp_dir().'/process_image_handler_test_'.bin2hex(random_bytes(8));
        mkdir($this->tmpDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    public function testHandlerProcessesUploadAndDispatchesAnalyzeMessage(): void
    {
        $repository = $this->createRepository();
        $jobTrackingRepository = $this->createJobTrackingRepository();
        $service = $this->createImageService($repository);
        $bus = new InMemoryMessageBus();
        $handler = new ProcessImageUploadMessageHandler($service, $bus, $jobTrackingRepository, new NullLogger());

        $stagedPath = $this->tmpDir.'/staged.fixture.jpg';
        copy($this->createJpegFixture(1200, 800), $stagedPath);
        $jobId = str_repeat('a', 32);
        $jobTrackingRepository->createQueued($jobId);

        $handler(new ProcessImageUploadMessage($jobId, $stagedPath, 'fixture.jpg', 'image/jpeg'));

        self::assertFileDoesNotExist($stagedPath);
        self::assertCount(1, $bus->messages);
        self::assertInstanceOf(AnalyzeImageMessage::class, $bus->messages[0]);
        /** @var AnalyzeImageMessage $analyzeMessage */
        $analyzeMessage = $bus->messages[0];
        self::assertSame(1, $analyzeMessage->imageId());
        self::assertIsArray($repository->find(1));
        $job = $jobTrackingRepository->findByJobId($jobId);
        self::assertNotNull($job);
        self::assertSame('completed', $job['status']);
        self::assertSame(1, (int) $job['image_id']);
        self::assertNull($job['error']);
    }

    public function testHandlerStillPersistsImageWhenAnalyzeDispatchFails(): void
    {
        $repository = $this->createRepository();
        $jobTrackingRepository = $this->createJobTrackingRepository();
        $service = $this->createImageService($repository);
        $handler = new ProcessImageUploadMessageHandler($service, new ThrowingMessageBus(), $jobTrackingRepository, new NullLogger());

        $stagedPath = $this->tmpDir.'/staged.dispatch-fail.jpg';
        copy($this->createJpegFixture(640, 480), $stagedPath);
        $jobId = str_repeat('b', 32);
        $jobTrackingRepository->createQueued($jobId);

        $handler(new ProcessImageUploadMessage($jobId, $stagedPath, 'dispatch-fail.jpg', 'image/jpeg'));

        self::assertFileDoesNotExist($stagedPath);
        self::assertIsArray($repository->find(1));
        $job = $jobTrackingRepository->findByJobId($jobId);
        self::assertNotNull($job);
        self::assertSame('completed', $job['status']);
        self::assertSame(1, (int) $job['image_id']);
    }

    public function testHandlerMarksJobFailedOnProcessingError(): void
    {
        $repository = $this->createRepository();
        $jobTrackingRepository = $this->createJobTrackingRepository();
        $service = $this->createImageService($repository);
        $handler = new ProcessImageUploadMessageHandler($service, new InMemoryMessageBus(), $jobTrackingRepository, new NullLogger());
        $jobId = str_repeat('c', 32);
        $jobTrackingRepository->createQueued($jobId);

        try {
            $handler(new ProcessImageUploadMessage($jobId, $this->tmpDir.'/missing.jpg', 'missing.jpg', 'image/jpeg'));
            self::fail('Expected processing error was not thrown.');
        } catch (UnrecoverableMessageHandlingException $exception) {
            self::assertStringContainsString('not found', $exception->getMessage());
        }

        $job = $jobTrackingRepository->findByJobId($jobId);
        self::assertNotNull($job);
        self::assertSame('failed', $job['status']);
        self::assertNull($job['image_id']);
        self::assertIsString($job['error']);
    }

    private function createRepository(): ImageRepository
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        return new ImageRepository($connection);
    }

    private function createImageService(ImageRepository $repository): ImageService
    {
        $storageRoot = $this->tmpDir.'/project/var/storage/images';
        mkdir($storageRoot, 0775, true);

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
            new NullLogger(),
        );
    }

    private function createJobTrackingRepository(): JobTrackingRepository
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        return new JobTrackingRepository($connection);
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
