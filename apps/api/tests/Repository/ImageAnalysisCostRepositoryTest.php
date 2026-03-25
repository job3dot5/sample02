<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Repository\ImageAnalysisCostRepository;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class ImageAnalysisCostRepositoryTest extends TestCase
{
    public function testCreateAndFindLatestByImageId(): void
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $repository = new ImageAnalysisCostRepository($connection);

        $repository->create(
            imageId: 42,
            model: 'gpt-4.1-nano',
            inputTokens: 1200,
            outputTokens: 400,
            totalTokens: 1600,
            estimatedCost: 0.0012,
        );

        $saved = $repository->findLatestByImageId(42);
        self::assertNotNull($saved);
        self::assertSame(42, (int) $saved['image_id']);
        self::assertSame('gpt-4.1-nano', $saved['model']);
        self::assertSame(1200, (int) $saved['input_tokens']);
        self::assertSame(400, (int) $saved['output_tokens']);
        self::assertSame(1600, (int) $saved['total_tokens']);
        self::assertSame(0.0012, (float) $saved['estimated_cost']);
    }
}
