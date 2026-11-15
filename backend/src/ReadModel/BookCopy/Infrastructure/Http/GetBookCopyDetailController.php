<?php

declare(strict_types=1);

namespace App\ReadModel\BookCopy\Infrastructure\Http;

use App\Identity\Application\PublicApi\CurrentUserId;
use App\ReadModel\BookCopy\Application\BookCopyReadStore;
use App\Shared\Infrastructure\Http\ApiResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class GetBookCopyDetailController
{
    public function __construct(private BookCopyReadStore $store, private CurrentUserId $currentUserId)
    {
    }

    #[Route('/api/library/book-copies/{id}', name: 'api_library_bookCopy_detail', methods: ['GET'])]
    public function __invoke(string $id): JsonResponse
    {
        $viewerId = $this->currentUserId->get();
        if (null === $viewerId) {
            throw new AccessDeniedException();
        }
        $result = $this->store->detail($id, $viewerId);
        if (null === $result) {
            return ApiResponse::error('not_found', 'Resource not available.', 404, ['Cache-Control' => 'private, no-store']);
        }
        return ApiResponse::success(['bookCopy' => $result], headers: ['Cache-Control' => 'private, no-store']);
    }
}
