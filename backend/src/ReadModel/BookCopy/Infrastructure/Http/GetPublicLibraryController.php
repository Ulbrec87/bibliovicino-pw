<?php

declare(strict_types=1);

namespace App\ReadModel\BookCopy\Infrastructure\Http;

use App\Identity\Application\PublicApi\CurrentUserId;
use App\ReadModel\BookCopy\Application\BookCopyReadStore;
use App\Shared\Infrastructure\Http\ApiResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class GetPublicLibraryController
{
    public function __construct(private BookCopyReadStore $store, private CurrentUserId $currentUserId)
    {
    }

    #[Route('/api/library/users/{id}/book-copies', name: 'api_library_library_detail', methods: ['GET'])]
    public function __invoke(string $id): JsonResponse
    {
        if (null === $this->currentUserId->get()) {
            throw new AccessDeniedException();
        }
        $result = $this->store->publicLibrary($id);
        if (null === $result) {
            return ApiResponse::error('not_found', 'Resource not available.', 404, ['Cache-Control' => 'private, no-store']);
        }
        return ApiResponse::success(['library' => $result], headers: ['Cache-Control' => 'private, no-store']);
    }
}
