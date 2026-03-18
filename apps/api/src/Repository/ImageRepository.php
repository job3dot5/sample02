<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class ImageRepository
{
    private const TABLE_NAME = 'image';
    private const UPDATED_AT_COLUMN = 'updated_at';
    private const ANALYSIS_STATUS_COLUMN = 'analysis_status';
    private const ANALYSIS_JSON_COLUMN = 'analysis_json';
    private const ANALYSIS_ERROR_COLUMN = 'analysis_error';
    private const ANALYSIS_MODEL_COLUMN = 'analysis_model';
    private const ANALYSIS_UPDATED_AT_COLUMN = 'analysis_updated_at';

    private bool $tableReady = false;

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param array{
     *   original_filename:string,
     *   mime_type:string,
     *   size_bytes:int,
     *   width:int,
     *   height:int,
     *   orientation:string,
     *   metadata_json:string,
     *   original_path:string,
     *   thumbnail_path:string,
     *   resized_path:string
     * } $data
     */
    public function create(array $data): int
    {
        $this->ensureTableExists();
        $now = (new \DateTimeImmutable())->format(\DATE_ATOM);

        $this->connection->insert(self::TABLE_NAME, [
            'created_at' => $now,
            self::UPDATED_AT_COLUMN => $now,
            'original_filename' => $data['original_filename'],
            'mime_type' => $data['mime_type'],
            'size_bytes' => $data['size_bytes'],
            'width' => $data['width'],
            'height' => $data['height'],
            'orientation' => $data['orientation'],
            'metadata_json' => $data['metadata_json'],
            'original_path' => $data['original_path'],
            'thumbnail_path' => $data['thumbnail_path'],
            'resized_path' => $data['resized_path'],
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function markAnalysisQueued(int $imageId): void
    {
        $this->saveAnalysisPayload($imageId, 'queued', null, null, null);
    }

    /**
     * @param array<string,mixed>|null $analysis
     */
    public function saveAnalysisPayload(
        int $imageId,
        string $status,
        ?array $analysis,
        ?string $model,
        ?string $error,
    ): void {
        $this->ensureTableExists();

        $this->connection->update(self::TABLE_NAME, [
            self::ANALYSIS_STATUS_COLUMN => $status,
            self::ANALYSIS_JSON_COLUMN => null === $analysis ? null : json_encode($analysis, \JSON_THROW_ON_ERROR),
            self::ANALYSIS_MODEL_COLUMN => $model,
            self::ANALYSIS_ERROR_COLUMN => $error,
            self::ANALYSIS_UPDATED_AT_COLUMN => (new \DateTimeImmutable())->format(\DATE_ATOM),
            self::UPDATED_AT_COLUMN => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ], ['id' => $imageId]);
    }

    public function markAnalysisSkipped(int $imageId, string $reason): void
    {
        $this->saveAnalysisPayload($imageId, 'skipped', null, null, $reason);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function find(int $id): ?array
    {
        $this->ensureTableExists();
        $image = $this->connection->fetchAssociative(
            sprintf('SELECT * FROM %s WHERE id = :id', self::TABLE_NAME),
            ['id' => $id],
        );

        return false === $image ? null : $image;
    }

    /**
     * @return array{items:list<array<string,mixed>>,total:int}
     */
    public function listPaginated(int $page, int $perPage): array
    {
        $this->ensureTableExists();

        $offset = ($page - 1) * $perPage;
        $items = $this->connection->fetchAllAssociative(
            sprintf('SELECT * FROM %s ORDER BY id DESC LIMIT :limit OFFSET :offset', self::TABLE_NAME),
            [
                'limit' => $perPage,
                'offset' => $offset,
            ],
            [
                'limit' => ParameterType::INTEGER,
                'offset' => ParameterType::INTEGER,
            ],
        );
        $total = (int) $this->connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s', self::TABLE_NAME));

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    private function ensureTableExists(): void
    {
        if ($this->tableReady) {
            return;
        }

        $this->connection->executeStatement(sprintf(<<<'SQL'
CREATE TABLE IF NOT EXISTS %s (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  original_filename TEXT NOT NULL,
  mime_type TEXT NOT NULL,
  size_bytes INTEGER NOT NULL,
  width INTEGER NOT NULL,
  height INTEGER NOT NULL,
  orientation TEXT NOT NULL,
  metadata_json TEXT NOT NULL,
  original_path TEXT NOT NULL,
  thumbnail_path TEXT NOT NULL,
  resized_path TEXT NOT NULL,
  analysis_status TEXT DEFAULT NULL,
  analysis_json TEXT DEFAULT NULL,
  analysis_error TEXT DEFAULT NULL,
  analysis_model TEXT DEFAULT NULL,
  analysis_updated_at TEXT DEFAULT NULL
)
SQL,
            self::TABLE_NAME,
        ));

        $this->tableReady = true;
    }
}
