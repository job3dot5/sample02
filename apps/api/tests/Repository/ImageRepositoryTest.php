<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Repository\ImageRepository;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class ImageRepositoryTest extends TestCase
{
    public function testCreateAndFindImageRow(): void
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $repository = new ImageRepository($connection);

        $id = $repository->create([
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

        self::assertGreaterThan(0, $id);

        $saved = $repository->find($id);
        self::assertNotNull($saved);
        self::assertSame('photo.jpg', $saved['original_filename']);
        self::assertSame('image/jpeg', $saved['mime_type']);
        self::assertSame(1920, (int) $saved['width']);
        self::assertSame(1080, (int) $saved['height']);
        self::assertSame('landscape', $saved['orientation']);
    }
}
