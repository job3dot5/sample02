<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class ImageAnalysisCostRepository
{
    private const TABLE_NAME = 'image_analysis_cost';

    private bool $tableReady = false;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function create(
        int $imageId,
        string $model,
        int $inputTokens,
        int $outputTokens,
        int $totalTokens,
        float $estimatedCost,
    ): void {
        $this->ensureTableExists();

        $this->connection->insert(self::TABLE_NAME, [
            'image_id' => $imageId,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => $totalTokens,
            'estimated_cost' => $estimatedCost,
            'created_at' => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ]);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findLatestByImageId(int $imageId): ?array
    {
        $this->ensureTableExists();
        $row = $this->connection->fetchAssociative(
            sprintf('SELECT * FROM %s WHERE image_id = :image_id ORDER BY id DESC LIMIT 1', self::TABLE_NAME),
            ['image_id' => $imageId],
        );

        return false === $row ? null : $row;
    }

    /**
     * @param list<int> $imageIds
     *
     * @return array<int,array<string,mixed>>
     */
    public function findLatestByImageIds(array $imageIds): array
    {
        $this->ensureTableExists();
        $imageIds = array_values(array_unique(array_map('intval', $imageIds)));
        if ([] === $imageIds) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($imageIds), '?'));
        $sql = sprintf(
            'SELECT c.* FROM %1$s c
             INNER JOIN (
               SELECT image_id, MAX(id) AS latest_id
               FROM %1$s
               WHERE image_id IN (%2$s)
               GROUP BY image_id
             ) latest ON latest.latest_id = c.id',
            self::TABLE_NAME,
            $placeholders,
        );

        $types = array_fill(0, count($imageIds), ParameterType::INTEGER);
        $rows = $this->connection->fetchAllAssociative($sql, $imageIds, $types);
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) $row['image_id']] = $row;
        }

        return $indexed;
    }

    private function ensureTableExists(): void
    {
        if ($this->tableReady) {
            return;
        }

        $this->connection->executeStatement(sprintf(<<<'SQL'
CREATE TABLE IF NOT EXISTS %s (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  image_id INTEGER NOT NULL,
  model TEXT NOT NULL,
  input_tokens INTEGER NOT NULL,
  output_tokens INTEGER NOT NULL,
  total_tokens INTEGER NOT NULL,
  estimated_cost REAL NOT NULL,
  created_at TEXT NOT NULL
)
SQL,
            self::TABLE_NAME,
        ));

        $this->tableReady = true;
    }
}
