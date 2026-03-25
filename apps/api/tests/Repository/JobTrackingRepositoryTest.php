<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Repository\JobTrackingRepository;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class JobTrackingRepositoryTest extends TestCase
{
    public function testCreateAndUpdateJobTrackingRow(): void
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $repository = new JobTrackingRepository($connection);
        $jobId = str_repeat('a', 32);

        $repository->createQueued($jobId);
        $queued = $repository->findByJobId($jobId);
        self::assertNotNull($queued);
        self::assertSame('queued', $queued['status']);
        self::assertNull($queued['image_id']);
        self::assertNull($queued['error']);

        $repository->markProcessing($jobId);
        $processing = $repository->findByJobId($jobId);
        self::assertNotNull($processing);
        self::assertSame('processing', $processing['status']);

        $repository->markCompleted($jobId, 42);
        $completed = $repository->findByJobId($jobId);
        self::assertNotNull($completed);
        self::assertSame('completed', $completed['status']);
        self::assertSame(42, (int) $completed['image_id']);
        self::assertNull($completed['error']);
    }

    public function testMarkFailedStoresError(): void
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $repository = new JobTrackingRepository($connection);
        $jobId = str_repeat('b', 32);

        $repository->createQueued($jobId);
        $repository->markFailed($jobId, 'Worker failed.');
        $failed = $repository->findByJobId($jobId);

        self::assertNotNull($failed);
        self::assertSame('failed', $failed['status']);
        self::assertNull($failed['image_id']);
        self::assertSame('Worker failed.', $failed['error']);
    }
}
