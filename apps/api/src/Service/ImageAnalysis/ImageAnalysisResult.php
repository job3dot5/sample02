<?php

declare(strict_types=1);

namespace App\Service\ImageAnalysis;

/**
 * @phpstan-type ImageAnalysisArray array{description:string,tags:list<string>,category:string}
 */
final readonly class ImageAnalysisResult
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public string $description,
        public array $tags,
        public string $category,
    ) {
    }

    /**
     * @return ImageAnalysisArray
     */
    public function toArray(): array
    {
        return [
            'description' => $this->description,
            'tags' => $this->tags,
            'category' => $this->category,
        ];
    }
}
