<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Http;

use App\Identity\Application\PublicApi\CurrentUserId;
use App\ReadModel\BookCopy\Application\BookCopyCollectionStore;
use App\Shared\Infrastructure\Http\ApiResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class MyLibraryController
{
    public function __construct(private BookCopyCollectionStore $store, private CurrentUserId $currentUserId)
    {
    }

    #[Route('/api/library/my-book-copies', name: 'api_library_my_book_copies', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $ownerId = $this->currentUserId->get();
        if (null === $ownerId) {
            throw new AccessDeniedException();
        }
        return ApiResponse::success(['bookCopies' => $this->store->ownedCopies($ownerId)], headers: ['Cache-Control' => 'private, no-store']);
    }
}
