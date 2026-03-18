<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ImageRepository;
use App\Service\ImageUploadQueueService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

final class ImageController extends AbstractApiController
{
    private const DEFAULT_PAGE = 1;
    private const DEFAULT_PER_PAGE = 20;
    private const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly int $maxUploadSizeBytes,
        private readonly string $projectDir,
        string $urnErrorPrefix,
    ) {
        parent::__construct($urnErrorPrefix);
    }

    #[Route('/image/{id}', name: 'image_render', methods: ['GET'])]
    public function renderImage(int $id, Request $request, ImageRepository $imageRepository): Response
    {
        $variant = strtolower($request->query->getString('variant', 'original'));
        $pathField = match ($variant) {
            'original' => 'original_path',
            'thumbnail' => 'thumbnail_path',
            'resized' => 'resized_path',
            default => null,
        };

        if (null === $pathField) {
            return $this->problem(
                Response::HTTP_BAD_REQUEST,
                'Bad Request',
                $this->errorType('image-variant-invalid'),
                'Query parameter "variant" must be one of: original, thumbnail, resized.',
            );
        }

        $image = $imageRepository->find($id);
        if (null === $image) {
            return $this->problem(
                Response::HTTP_NOT_FOUND,
                'Not Found',
                $this->errorType('image-not-found'),
                sprintf('Image with id "%d" was not found.', $id),
            );
        }

        $relativePath = (string) ($image[$pathField] ?? '');
        $absolutePath = $this->resolveImagePath($relativePath);
        if (null === $absolutePath) {
            return $this->problem(
                Response::HTTP_NOT_FOUND,
                'Not Found',
                $this->errorType('image-file-not-found'),
                sprintf('Image file for id "%d" (%s) was not found on disk.', $id, $variant),
            );
        }

        $response = new BinaryFileResponse($absolutePath);
        $response->headers->set('Content-Type', (string) ($image['mime_type'] ?? 'application/octet-stream'));
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            (string) ($image['original_filename'] ?? basename($absolutePath)),
        );

        return $response;
    }

    private function resolveImagePath(string $relativePath): ?string
    {
        if ('' === $relativePath) {
            return null;
        }

        $normalized = ltrim($relativePath, '/');
        $path = $this->projectDir.'/'.$normalized;

        if (is_file($path)) {
            return $path;
        }

        return null;
    }

    #[Route('/images', name: 'image_upload', methods: ['POST'])]
    public function upload(Request $request, ImageUploadQueueService $imageUploadQueueService): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return $this->problem(
                Response::HTTP_BAD_REQUEST,
                'Bad Request',
                $this->errorType('image-file-required'),
                'Multipart field "file" is required.',
            );
        }

        if (!$file->isValid()) {
            return $this->problem(
                Response::HTTP_BAD_REQUEST,
                'Bad Request',
                $this->errorType('image-upload-invalid'),
                'Uploaded image is invalid.',
            );
        }

        $size = $file->getSize();
        if (!is_int($size)) {
            return $this->problem(
                Response::HTTP_BAD_REQUEST,
                'Bad Request',
                $this->errorType('image-size-unavailable'),
                'Unable to determine uploaded image size.',
            );
        }

        if ($size > $this->maxUploadSizeBytes) {
            return $this->problem(
                Response::HTTP_REQUEST_ENTITY_TOO_LARGE,
                'Payload Too Large',
                $this->errorType('image-too-large'),
                'Uploaded image exceeds the 5 MB limit.',
            );
        }

        try {
            $jobId = $imageUploadQueueService->queueUpload($file);
        } catch (\InvalidArgumentException $exception) {
            return $this->problem(
                Response::HTTP_BAD_REQUEST,
                'Bad Request',
                $this->errorType('image-upload-invalid'),
                $exception->getMessage(),
            );
        } catch (\RuntimeException $exception) {
            return $this->problem(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'Image queueing failed',
                $this->errorType('image-queueing-failed'),
                $exception->getMessage(),
            );
        }

        return new JsonResponse([
            'data' => [
                'job_id' => $jobId,
                'status' => 'queued',
            ],
        ], Response::HTTP_ACCEPTED);
    }

    #[Route('/images', name: 'image_list', methods: ['GET'])]
    public function list(Request $request, ImageRepository $imageRepository): JsonResponse
    {
        $pageInput = $request->query->get('page');
        $perPageInput = $request->query->get('per_page');

        $page = null === $pageInput ? self::DEFAULT_PAGE : filter_var($pageInput, \FILTER_VALIDATE_INT);
        $perPage = null === $perPageInput ? self::DEFAULT_PER_PAGE : filter_var($perPageInput, \FILTER_VALIDATE_INT);

        if (!is_int($page) || $page < 1) {
            return $this->problem(
                Response::HTTP_BAD_REQUEST,
                'Bad Request',
                $this->errorType('image-list-page-invalid'),
                'Query parameter "page" must be an integer greater than or equal to 1.',
            );
        }

        if (!is_int($perPage) || $perPage < 1 || $perPage > self::MAX_PER_PAGE) {
            return $this->problem(
                Response::HTTP_BAD_REQUEST,
                'Bad Request',
                $this->errorType('image-list-per-page-invalid'),
                sprintf('Query parameter "per_page" must be an integer between 1 and %d.', self::MAX_PER_PAGE),
            );
        }

        $result = $imageRepository->listPaginated($page, $perPage);
        $total = (int) $result['total'];
        $totalPages = (int) max(1, (int) ceil($total / $perPage));

        return new JsonResponse([
            'data' => array_map(
                fn (array $row): array => $this->normalizeImageListRow($row),
                $result['items'],
            ),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ]);
    }

    /**
     * @param array<string,mixed> $row
     *
     * @return array<string,mixed>
     */
    private function normalizeImageListRow(array $row): array
    {
        $id = (int) ($row['id'] ?? 0);
        $analysisJson = null;
        if (is_string($row['analysis_json'] ?? null) && '' !== $row['analysis_json']) {
            try {
                $analysisJson = json_decode($row['analysis_json'], true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $analysisJson = null;
            }
        }

        return [
            'id' => $id,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'original_filename' => (string) ($row['original_filename'] ?? ''),
            'mime_type' => (string) ($row['mime_type'] ?? ''),
            'size_bytes' => (int) ($row['size_bytes'] ?? 0),
            'width' => (int) ($row['width'] ?? 0),
            'height' => (int) ($row['height'] ?? 0),
            'orientation' => (string) ($row['orientation'] ?? ''),
            'image_urls' => [
                'original' => sprintf('/api/v1/image/%d?variant=original', $id),
                'thumbnail' => sprintf('/api/v1/image/%d?variant=thumbnail', $id),
                'resized' => sprintf('/api/v1/image/%d?variant=resized', $id),
            ],
            'analysis' => [
                'status' => $row['analysis_status'] ?? null,
                'result' => $analysisJson,
            ],
        ];
    }
}
