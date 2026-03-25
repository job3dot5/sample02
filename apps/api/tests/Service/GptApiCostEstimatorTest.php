<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ImageAnalysis\GptApiCostEstimator;
use PHPUnit\Framework\TestCase;

final class GptApiCostEstimatorTest extends TestCase
{
    public function testEstimateNormalizesVersionedModelAndComputesCost(): void
    {
        $estimator = new GptApiCostEstimator([
            'gpt-4.1-nano' => [
                'input' => 0.0005,
                'output' => 0.0015,
            ],
        ]);

        $estimate = $estimator->estimate('gpt-4.1-nano-2025-04-14', 1200, 400, 1600);

        self::assertNotNull($estimate);
        self::assertSame('gpt-4.1-nano', $estimate['model']);
        self::assertSame(1200, $estimate['input_tokens']);
        self::assertSame(400, $estimate['output_tokens']);
        self::assertSame(1600, $estimate['total_tokens']);
        self::assertSame(0.0012, $estimate['estimated_cost']);
    }

    public function testEstimateReturnsNullWhenModelPricingIsMissing(): void
    {
        $estimator = new GptApiCostEstimator([
            'gpt-4.1-nano' => [
                'input' => 0.0005,
                'output' => 0.0015,
            ],
        ]);

        self::assertNull($estimator->estimate('gpt-4.1-mini-2025-04-14', 10, 10, 20));
    }
}
