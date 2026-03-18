<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Message\AnalyzeImageMessage;
use App\MessageHandler\AnalyzeImageMessageHandler;
use App\Repository\ImageRepository;
use App\Service\ImageAnalysis\ImageAnalysisService;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class AnalyzeImageMessageHandlerTest extends TestCase
{
    public function testHandlerMarksAnalysisAsSkippedWhenApiKeyIsMissing(): void
    {
        $repository = $this->createRepository();
        $imageId = $this->createImageRow($repository);
        $handler = new AnalyzeImageMessageHandler(
            new ImageAnalysisService(
                $repository,
                new MockHttpClient(),
                '',
                'gpt-4.1-nano',
                'https://api.openai.com/v1/responses',
                '/var/www/api',
                new NullLogger(),
            ),
            new NullLogger(),
        );

        $handler(new AnalyzeImageMessage($imageId));

        $saved = $repository->find($imageId);
        self::assertIsArray($saved);
        self::assertSame('skipped', $saved['analysis_status']);
        self::assertSame('missing_openai_api_key', $saved['analysis_error']);
    }

    public function testHandlerStoresPendingAnalysisPayloadWhenApiKeyIsConfigured(): void
    {
        $repository = $this->createRepository();
        $imageId = $this->createImageRow($repository);
        $handler = new AnalyzeImageMessageHandler(
            new ImageAnalysisService(
                $repository,
                new MockHttpClient([
                    new MockResponse(json_encode([
                        'output_text' => '{"description":"A red object.","tags":["red","object"],"category":"object"}',
                    ], \JSON_THROW_ON_ERROR)),
                ]),
                'test-key',
                'gpt-4.1-nano',
                'https://api.openai.com/v1/responses',
                $this->createProjectDirWithResizedImage(),
                new NullLogger(),
            ),
            new NullLogger(),
        );

        $handler(new AnalyzeImageMessage($imageId));

        $saved = $repository->find($imageId);
        self::assertIsArray($saved);
        self::assertSame('completed', $saved['analysis_status']);
        self::assertSame('gpt-4.1-nano', $saved['analysis_model']);
        self::assertJson((string) $saved['analysis_json']);
    }

    private function createProjectDirWithResizedImage(): string
    {
        $projectDir = sys_get_temp_dir().'/analyze_handler_project_'.bin2hex(random_bytes(8));
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
