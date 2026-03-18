<?php

declare(strict_types=1);

namespace App\Service\ImageAnalysis;

final class ImageAnalysisContract
{
    public const PROMPT = <<<'PROMPT'
Analyze this image and return ONLY valid JSON (no markdown, no code fences, no extra text) with this exact shape:
{
  "description": "string",
  "tags": ["string"],
  "category": "string"
}

Rules:
- description: one short sentence (max 160 chars)
- tags: 0 to 5 concise lowercase tags, no duplicates
- category: one broad label (e.g. animal, object, landscape, food, person, vehicle, building, document, abstract, other)
- If uncertain, still return best-effort values and use "other" for category.
PROMPT;

    /**
     * @var array{
     *   type:string,
     *   additionalProperties:bool,
     *   required:list<string>,
     *   properties:array<string,array<string,mixed>>
     * }
     */
    public const JSON_SCHEMA = [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['description', 'tags', 'category'],
        'properties' => [
            'description' => [
                'type' => 'string',
            ],
            'tags' => [
                'type' => 'array',
                'maxItems' => 5,
                'items' => [
                    'type' => 'string',
                ],
            ],
            'category' => [
                'type' => 'string',
            ],
        ],
    ];

    private function __construct()
    {
    }
}
