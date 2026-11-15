<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Http;

use App\Identity\Application\PublicApi\CurrentUserId;
use App\Library\Application\Cover\ChangeCoverCommand;
use App\Library\Application\Cover\ChangeCoverHandler;
use App\Library\Application\Cover\CoverChoice;
use App\Library\Application\Cover\InvalidCoverImage;
use App\Library\Application\Cover\ReadCoverCommand;
use App\Library\Application\Cover\ReadCoverHandler;
use App\Library\Domain\BookCopy\BookCopyCover;
use App\Shared\Infrastructure\Http\ApiResponse;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class BookCopyCoverController
{
    public function __construct(private ChangeCoverHandler $change, private ReadCoverHandler $read, private CoverChoice $choices, private CurrentUserId $currentUser) {}

    #[Route('/api/library/book-copies/{id}/cover', methods: ['POST', 'PUT', 'DELETE'])]
    public function change(string $id, Request $request): Response
    {
        $path = null;
        try {
            $cover = null;
            if ($request->isMethod('POST')) {
                $path = $this->uploadedImagePath($request);
            } elseif ($request->isMethod('PUT')) {
                $cover = $this->externalCover($request);
            }
            $this->change->handle(new ChangeCoverCommand($id, $this->currentUser->get(), $cover, $path));
            return new Response(status: 204);
        } catch (JsonException) {
            return ApiResponse::error('invalid_json', 'The request body must be valid JSON.', 400);
        } catch (InvalidCoverImage $exception) {
            return ApiResponse::error($exception->tooLarge ? 'cover_too_large' : 'invalid_cover_image', $exception->tooLarge ? 'La copertina non può superare 10 MB.' : 'Usa una immagine JPEG, PNG o WebP valida.', $exception->tooLarge ? 413 : 422);
        } catch (\InvalidArgumentException) {
            return ApiResponse::validationError(['cover' => ['Seleziona un riferimento valido.']]);
        } finally {
            if (null !== $path && is_file($path)) {
                unlink($path);
            }
        }
    }

    #[Route('/api/library/book-copies/{id}/cover/{variant}', requirements: ['variant' => 'thumbnail|large'], methods: ['GET'])]
    public function read(string $id, string $variant): Response
    {
        $stream = $this->read->handle(new ReadCoverCommand($id, $this->currentUser->get(), $variant));
        return new StreamedResponse(static function () use ($stream): void {
            try { fpassthru($stream); } finally { fclose($stream); }
        }, headers: ['Content-Type' => 'image/webp']);
    }

    private function uploadedImagePath(Request $request): string
    {
        $image = $request->files->get('image');
        if (!$image instanceof UploadedFile || !$image->isValid() || count($request->files->all()) !== 1 || [] !== $request->request->all()) {
            $tooLarge = (int) $request->server->get('CONTENT_LENGTH', 0) > 10000000
                || ($image instanceof UploadedFile && in_array($image->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true));
            throw new InvalidCoverImage($tooLarge);
        }
        return $image->getPathname();
    }

    private function externalCover(Request $request): BookCopyCover
    {
        $input = $request->toArray();
        if (array_diff(array_keys($input), ['source', 'reference']) || ($input['source'] ?? null) !== 'external') {
            throw new \InvalidArgumentException();
        }
        return $this->choices->parse($input);
    }
}
