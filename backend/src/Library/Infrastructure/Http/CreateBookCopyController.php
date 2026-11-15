<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Http;

use App\Identity\Application\PublicApi\CurrentUserId;
use App\Library\Application\CreateBookCopy\BookAlreadyExists;
use App\Library\Application\CreateBookCopy\BookUnavailable;
use App\Library\Application\CreateBookCopy\CreateBookCopyCommandFactory;
use App\Library\Application\CreateBookCopy\CreateBookCopyHandler;
use App\Library\Application\CreateBookCopy\DiscoveryLocationRequired;
use App\Library\Application\CreateBookCopy\InvalidBookCopyInput;
use App\Shared\Infrastructure\Http\ApiResponse;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class CreateBookCopyController
{
    public function __construct(
        private CreateBookCopyHandler $create,
        private CurrentUserId $currentUserId,
        private CreateBookCopyCommandFactory $commands,
    ) {
    }

    #[Route('/api/library/book-copies', name: 'api_library_book_copies_create', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $input = $request->toArray();
        } catch (JsonException) {
            return ApiResponse::error('invalid_json', 'The request body must be valid JSON.', Response::HTTP_BAD_REQUEST);
        }
        $ownerId = $this->currentUserId->get();
        if (null === $ownerId) {
            throw new AccessDeniedException();
        }
        try {
            $result = $this->create->handle($this->commands->fromArray($ownerId, $input));
        } catch (BookUnavailable) {
            return ApiResponse::error('book_not_found', 'The selected Book is no longer available.', Response::HTTP_NOT_FOUND);
        } catch (InvalidBookCopyInput $exception) {
            return ApiResponse::validationError($exception->fields);
        } catch (DiscoveryLocationRequired) {
            return ApiResponse::error('discovery_location_required', 'Configure an approximate zone before publishing.', Response::HTTP_CONFLICT);
        } catch (BookAlreadyExists $exception) {
            return new JsonResponse(['error' => [
                'code' => 'book_already_exists',
                'message' => 'A Book with this ISBN already exists.',
                'book' => $exception->book,
            ]], Response::HTTP_CONFLICT);
        }
        return ApiResponse::success(['bookCopy' => [
            'id' => $result->id,
            'bookId' => $result->bookId,
            'isPublished' => $result->isPublished,
        ]], Response::HTTP_CREATED, ['Cache-Control' => 'private, no-store']);
    }
}
