<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;

final class JobTrackingRepository
{
    private const TABLE_NAME = 'job_tracking';

    private bool $tableReady = false;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function createQueued(string $jobId): void
    {
        $this->ensureTableExists();
        $now = (new \DateTimeImmutable())->format(\DATE_ATOM);

        $this->connection->insert(self::TABLE_NAME, [
            'job_id' => $jobId,
            'status' => 'queued',
            'image_id' => null,
            'error' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function markProcessing(string $jobId): void
    {
        $this->updateStatus($jobId, 'processing', null, null);
    }

    public function markCompleted(string $jobId, int $imageId): void
    {
        $this->updateStatus($jobId, 'completed', $imageId, null);
    }

    public function markFailed(string $jobId, string $error): void
    {
        $this->updateStatus($jobId, 'failed', null, $error);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findByJobId(string $jobId): ?array
    {
        $this->ensureTableExists();
        $row = $this->connection->fetchAssociative(
            sprintf('SELECT * FROM %s WHERE job_id = :job_id', self::TABLE_NAME),
            ['job_id' => $jobId],
        );

        return false === $row ? null : $row;
    }

    private function updateStatus(string $jobId, string $status, ?int $imageId, ?string $error): void
    {
        $this->ensureTableExists();

        $this->connection->update(self::TABLE_NAME, [
            'status' => $status,
            'image_id' => $imageId,
            'error' => $error,
            'updated_at' => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ], ['job_id' => $jobId]);
    }

    private function ensureTableExists(): void
    {
        if ($this->tableReady) {
            return;
        }

        $this->connection->executeStatement(sprintf(<<<'SQL'
CREATE TABLE IF NOT EXISTS %s (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  job_id TEXT NOT NULL UNIQUE,
  status TEXT NOT NULL,
  image_id INTEGER DEFAULT NULL,
  error TEXT DEFAULT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
)
SQL,
            self::TABLE_NAME,
        ));

        $this->tableReady = true;
    }
}
