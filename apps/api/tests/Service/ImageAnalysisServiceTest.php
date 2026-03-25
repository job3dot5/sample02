<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Repository\ImageAnalysisCostRepository;
use App\Repository\ImageRepository;
use App\Service\ImageAnalysis\GptApiCostEstimator;
use App\Service\ImageAnalysis\ImageAnalysisService;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ImageAnalysisServiceTest extends TestCase
{
    public function testAnalyzeImageSkipsWhenApiKeyIsMissing(): void
    {
        $repository = $this->createRepository();
        $costRepository = $this->createCostRepository();
        $imageId = $this->createImageRow($repository);

        $service = new ImageAnalysisService(
            $repository,
            $costRepository,
            $this->createCostEstimator(),
            new MockHttpClient(),
            '',
            'gpt-4.1-nano',
            'https://api.openai.com/v1/responses',
            '/var/www/api',
            new NullLogger(),
        );

        $service->analyzeImage($imageId);
        $saved = $repository->find($imageId);

        self::assertIsArray($saved);
        self::assertSame('skipped', $saved['analysis_status']);
        self::assertSame('missing_openai_api_key', $saved['analysis_error']);
        self::assertNull($saved['analysis_json']);
        self::assertNull($costRepository->findLatestByImageId($imageId));
    }

    public function testAnalyzeImageStoresPendingPayloadWhenApiKeyIsSet(): void
    {
        $repository = $this->createRepository();
        $costRepository = $this->createCostRepository();
        $imageId = $this->createImageRow($repository);

        $service = new ImageAnalysisService(
            $repository,
            $costRepository,
            $this->createCostEstimator(),
            new MockHttpClient([
                new MockResponse(json_encode([
                    'model' => 'gpt-4.1-nano-2025-04-14',
                    'usage' => [
                        'input_tokens' => 1553,
                        'output_tokens' => 36,
                        'total_tokens' => 1589,
                    ],
                    'output_text' => json_encode([
                        'description' => 'A small landscape photo.',
                        'tags' => ['landscape', 'sky', 'nature'],
                        'category' => 'landscape',
                    ], \JSON_THROW_ON_ERROR),
                ], \JSON_THROW_ON_ERROR)),
            ]),
            'test-key',
            'gpt-4.1-nano',
            'https://api.openai.com/v1/responses',
            $this->createProjectDirWithResizedImage(),
            new NullLogger(),
        );

        $service->analyzeImage($imageId);
        $saved = $repository->find($imageId);

        self::assertIsArray($saved);
        self::assertSame('completed', $saved['analysis_status']);
        self::assertSame('gpt-4.1-nano', $saved['analysis_model']);
        self::assertNotEmpty($saved['analysis_json']);

        $savedCost = $costRepository->findLatestByImageId($imageId);
        self::assertNotNull($savedCost);
        self::assertSame('gpt-4.1-nano', $savedCost['model']);
        self::assertSame(1553, (int) $savedCost['input_tokens']);
        self::assertSame(36, (int) $savedCost['output_tokens']);
        self::assertSame(1589, (int) $savedCost['total_tokens']);
        self::assertSame(0.0008305, (float) $savedCost['estimated_cost']);
    }

    private function createProjectDirWithResizedImage(): string
    {
        $projectDir = sys_get_temp_dir().'/image_analysis_service_project_'.bin2hex(random_bytes(8));
        $path = $projectDir.'/var/storage/images/resized/2026-03';
        mkdir($path, 0775, true);
        file_put_contents($path.'/photo.resized.jpg', 'fake-image-content');

        return $projectDir;
    }

    private function createRepository(): ImageRepository
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        return new ImageRepository($connection);
    }

    private function createCostRepository(): ImageAnalysisCostRepository
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        return new ImageAnalysisCostRepository($connection);
    }

    private function createCostEstimator(): GptApiCostEstimator
    {
        return new GptApiCostEstimator([
            'gpt-4.1-nano' => [
                'input' => 0.0005,
                'output' => 0.0015,
            ],
        ]);
    }

    private function createImageRow(ImageRepository $repository): int
    {
        return $repository->create([
            'original_filename' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 12345,
            'width' => 1920,
            'height' => 1080,
            'orientation' => 'landscape',
            'metadata_json' => '{"processor":"imagick"}',
            'original_path' => 'var/storage/images/original/2026-03/photo.jpg',
            'thumbnail_path' => 'var/storage/images/thumbnail/2026-03/photo.thumb.jpg',
            'resized_path' => 'var/storage/images/resized/2026-03/photo.resized.jpg',
        ]);
    }
}
