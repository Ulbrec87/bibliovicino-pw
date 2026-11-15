<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Http;

use App\Identity\Application\PublicApi\CurrentUserId;
use App\Library\Application\UpdateBookCopyNotes\UpdateBookCopyNotesCommand;
use App\Library\Application\UpdateBookCopyNotes\UpdateBookCopyNotesHandler;
use App\Shared\Infrastructure\Http\ApiResponse;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class UpdateBookCopyNotesController
{
    public function __construct(
        private UpdateBookCopyNotesHandler $handler,
        private CurrentUserId $currentUserId,
    ) {
    }

    #[Route('/api/library/book-copies/{id}/notes', name: 'api_library_book_copy_notes', methods: ['PATCH'])]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        $ownerId = $this->currentUserId->get();
        if (null === $ownerId) {
            throw new AccessDeniedException();
        }

        try {
            $input = $request->toArray();
        } catch (JsonException) {
            return ApiResponse::error('invalid_json', 'The request body must be valid JSON.', Response::HTTP_BAD_REQUEST);
        }

        if (!$this->hasValidNotes($input)) {
            return ApiResponse::validationError(['notes' => ['Invia soltanto le note, fino a 1100 caratteri, oppure null.']]);
        }

        $command = new UpdateBookCopyNotesCommand($ownerId, $id, $input['notes']);
        $this->handler->handle($command);

        return ApiResponse::success([], Response::HTTP_NO_CONTENT, ['Cache-Control' => 'private, no-store']);
    }

    /** @param array<mixed> $input */
    private function hasValidNotes(array $input): bool
    {
        if (!array_key_exists('notes', $input) || count($input) !== 1) {
            return false;
        }

        $notes = $input['notes'];
        if (null === $notes) {
            return true;
        }

        return is_string($notes) && mb_strlen($notes) <= 1100;
    }
}
