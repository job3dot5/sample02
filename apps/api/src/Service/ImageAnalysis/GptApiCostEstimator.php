<?php

declare(strict_types=1);

namespace App\Service\ImageAnalysis;

final readonly class GptApiCostEstimator
{
    /**
     * @param array<string,array{input:float|int,output:float|int}> $pricing
     */
    public function __construct(private array $pricing)
    {
    }

    /**
     * @return array{model:string,input_tokens:int,output_tokens:int,total_tokens:int,estimated_cost:float}|null
     */
    public function estimate(string $model, int $inputTokens, int $outputTokens, int $totalTokens): ?array
    {
        $normalizedModel = $this->normalizeModel($model);
        $rates = $this->pricing[$normalizedModel] ?? null;
        if (!is_array($rates)) {
            return null;
        }

        $inputRate = (float) $rates['input'];
        $outputRate = (float) $rates['output'];

        // Rates are configured per 1,000 tokens.
        $estimatedCost = (($inputTokens / 1000) * $inputRate) + (($outputTokens / 1000) * $outputRate);

        return [
            'model' => $normalizedModel,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => $totalTokens,
            'estimated_cost' => round($estimatedCost, 10),
        ];
    }

    private function normalizeModel(string $model): string
    {
        $trimmed = trim($model);
        if ('' === $trimmed) {
            return $trimmed;
        }

        return (string) preg_replace('/-\d{4}-\d{2}-\d{2}$/', '', $trimmed);
    }
}
