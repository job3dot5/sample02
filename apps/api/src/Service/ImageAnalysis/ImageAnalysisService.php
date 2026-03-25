<?php

declare(strict_types=1);

namespace App\Service\ImageAnalysis;

use App\Repository\ImageAnalysisCostRepository;
use App\Repository\ImageRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ImageAnalysisService
{
    public function __construct(
        private ImageRepository $imageRepository,
        private ImageAnalysisCostRepository $imageAnalysisCostRepository,
        private GptApiCostEstimator $gptApiCostEstimator,
        private HttpClientInterface $httpClient,
        private ?string $openAiApiKey,
        private string $openAiModel,
        private string $openAiEndpoint,
        private string $projectDir,
        private LoggerInterface $imageProcessingLogger,
    ) {
    }

    public function analyzeImage(int $imageId): void
    {
        $image = $this->imageRepository->find($imageId);
        if (null === $image) {
            $this->imageProcessingLogger->warning('image.analysis.image_not_found', [
                'image_id' => $imageId,
            ]);

            return;
        }

        $this->imageRepository->markAnalysisQueued($imageId);

        $apiKey = trim((string) $this->openAiApiKey);
        if ('' === $apiKey) {
            $this->imageRepository->markAnalysisSkipped($imageId, 'missing_openai_api_key');
            $this->imageProcessingLogger->info('image.analysis.skipped', [
                'image_id' => $imageId,
                'reason' => 'missing_openai_api_key',
            ]);

            return;
        }

        $resizedPath = (string) ($image['resized_path'] ?? '');
        if ('' === $resizedPath) {
            throw new \InvalidArgumentException('Image has no resized_path available for AI analysis.');
        }

        $absolutePath = $this->projectDir.'/'.ltrim($resizedPath, '/');
        if (!is_file($absolutePath)) {
            throw new \InvalidArgumentException(sprintf('Resized image file was not found at "%s".', $absolutePath));
        }

        try {
            $apiResponse = $this->callVisionApi($absolutePath, $apiKey);
            $this->imageRepository->saveAnalysisPayload(
                $imageId,
                'completed',
                [
                    ...$apiResponse->analysis->toArray(),
                    'prompt' => ImageAnalysisContract::PROMPT,
                    'image_variant' => 'resized',
                ],
                model: $this->openAiModel,
                error: null,
            );
            $this->persistEstimatedCost($imageId, $apiResponse);
        } catch (\Throwable $exception) {
            $this->imageRepository->saveAnalysisPayload(
                $imageId,
                'failed',
                null,
                model: $this->openAiModel,
                error: substr($exception->getMessage(), 0, 1000),
            );
            throw new \RuntimeException('OpenAI vision analysis failed.', 0, $exception);
        }
    }

    private function callVisionApi(string $absolutePath, string $apiKey): ImageAnalysisApiResponse
    {
        $raw = file_get_contents($absolutePath);
        if (false === $raw) {
            throw new \RuntimeException(sprintf('Unable to read resized image at "%s".', $absolutePath));
        }
        $mime = mime_content_type($absolutePath) ?: 'image/png';
        $dataUrl = sprintf('data:%s;base64,%s', $mime, base64_encode($raw));

        $response = $this->httpClient->request('POST', $this->openAiEndpoint, [
            'headers' => [
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 20,
            'json' => [
                'model' => $this->openAiModel,
                'input' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => ImageAnalysisContract::PROMPT,
                            ],
                            [
                                'type' => 'input_image',
                                'image_url' => $dataUrl,
                            ],
                        ],
                    ],
                ],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'image_analysis',
                        'schema' => ImageAnalysisContract::JSON_SCHEMA,
                    ],
                ],
            ],
        ]);

        $payload = $response->toArray();
        $rawOutput = $this->extractOutputText($payload);
        $analysis = $this->parseRawResponse($rawOutput);
        $usage = $this->extractUsage($payload);

        return new ImageAnalysisApiResponse(
            $analysis,
            $this->extractModel($payload),
            $usage['input_tokens'],
            $usage['output_tokens'],
            $usage['total_tokens'],
        );
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function extractOutputText(array $payload): string
    {
        // shortcut (rare)
        if (!empty($payload['output_text'])) {
            return trim((string) $payload['output_text']);
        }

        $content = $payload['output'][0]['content'][0] ?? null;

        if (!is_array($content)) {
            throw new \RuntimeException('OpenAI response missing content.');
        }

        // JSON direct
        if (isset($content['json']) && is_array($content['json'])) {
            return json_encode($content['json'], JSON_THROW_ON_ERROR);
        }

        // fallback : TEXT
        if (isset($content['text']) && is_string($content['text'])) {
            return trim($content['text']);
        }

        throw new \RuntimeException('OpenAI response did not include usable output.');
    }

    /**
     * @param array<string,mixed> $payload
     *
     * @return array{input_tokens:int,output_tokens:int,total_tokens:int}
     */
    private function extractUsage(array $payload): array
    {
        $usage = is_array($payload['usage'] ?? null) ? $payload['usage'] : [];
        $inputTokens = (int) ($usage['input_tokens'] ?? 0);
        $outputTokens = (int) ($usage['output_tokens'] ?? 0);
        $totalTokens = (int) ($usage['total_tokens'] ?? ($inputTokens + $outputTokens));

        return [
            'input_tokens' => max(0, $inputTokens),
            'output_tokens' => max(0, $outputTokens),
            'total_tokens' => max(0, $totalTokens),
        ];
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function extractModel(array $payload): string
    {
        $model = trim((string) ($payload['model'] ?? ''));

        return '' !== $model ? $model : $this->openAiModel;
    }

    private function parseRawResponse(string $raw): ImageAnalysisResult
    {
        $analysis = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        $tags = [];
        foreach ((array) ($analysis['tags'] ?? []) as $tag) {
            $value = trim((string) $tag);
            if ('' !== $value) {
                $tags[] = $value;
            }
        }
        sort($tags);

        return new ImageAnalysisResult(
            trim((string) ($analysis['description'] ?? '')),
            array_values(array_unique(array_map('trim', $tags))),
            trim((string) ($analysis['category'] ?? '')),
        );
    }

    private function persistEstimatedCost(int $imageId, ImageAnalysisApiResponse $apiResponse): void
    {
        $estimate = $this->gptApiCostEstimator->estimate(
            $apiResponse->model,
            $apiResponse->inputTokens,
            $apiResponse->outputTokens,
            $apiResponse->totalTokens,
        );

        if (null === $estimate) {
            $this->imageProcessingLogger->warning('image.analysis.cost.pricing_not_found', [
                'image_id' => $imageId,
                'model' => $apiResponse->model,
            ]);

            return;
        }

        $this->imageAnalysisCostRepository->create(
            $imageId,
            $estimate['model'],
            $estimate['input_tokens'],
            $estimate['output_tokens'],
            $estimate['total_tokens'],
            $estimate['estimated_cost'],
        );
    }
}
