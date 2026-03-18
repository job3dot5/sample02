<?php

declare(strict_types=1);

namespace App\Service\ImageAnalysis;

use App\Repository\ImageRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final readonly class ImageAnalysisService
{
    public function __construct(
        private ImageRepository $imageRepository,
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
            $analysis = $this->callVisionApi($absolutePath, $apiKey);
            $this->imageRepository->saveAnalysisPayload(
                $imageId,
                'completed',
                [
                    ...$analysis->toArray(),
                    'prompt' => ImageAnalysisContract::PROMPT,
                    'image_variant' => 'resized',
                ],
                model: $this->openAiModel,
                error: null,
            );
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

    private function callVisionApi(string $absolutePath, string $apiKey): ImageAnalysisResult
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

        $raw = $this->extractOutputText($response);

        return $this->parseRawResponse($raw);
    }

    private function extractOutputText(ResponseInterface $response): string
    {
        $payload = $response->toArray();

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
}
