<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;

final class ImageRepository
{
    private const TABLE_NAME = 'image';

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

        $this->connection->insert(self::TABLE_NAME, [
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
            'created_at' => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ]);

        return (int) $this->connection->lastInsertId();
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

    private function ensureTableExists(): void
    {
        if ($this->tableReady) {
            return;
        }

        $this->connection->executeStatement(sprintf(<<<'SQL'
CREATE TABLE IF NOT EXISTS %s (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
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
  created_at TEXT NOT NULL
)
SQL,
            self::TABLE_NAME,
        ));

        $this->tableReady = true;
    }
}
