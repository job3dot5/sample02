<?php

declare(strict_types=1);

namespace App\Service\ImageAnalysis;

final readonly class ImageAnalysisApiResponse
{
    public function __construct(
        public ImageAnalysisResult $analysis,
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
        public int $totalTokens,
    ) {
    }
}
