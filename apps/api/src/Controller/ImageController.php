<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ImageRepository;
use App\Service\ImageService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

final class ImageController extends AbstractApiController
{
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
        $candidates = [$this->projectDir.'/'.$normalized];
        if (!str_starts_with($normalized, 'var/')) {
            $candidates[] = $this->projectDir.'/var/'.$normalized;
        }

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    #[Route('/images', name: 'image_upload', methods: ['POST'])]
    public function upload(Request $request, ImageService $imageService): JsonResponse
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
            $savedImage = $imageService->handleUpload($file);
        } catch (\InvalidArgumentException $exception) {
            return $this->problem(
                Response::HTTP_BAD_REQUEST,
                'Bad Request',
                $this->errorType('image-upload-invalid'),
                $exception->getMessage(),
            );
        } catch (\Throwable $exception) {
            return $this->problem(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'Image processing failed',
                $this->errorType('image-processing-failed'),
                $exception->getMessage(),
            );
        }

        return new JsonResponse(['data' => $savedImage], Response::HTTP_CREATED);
    }
}
