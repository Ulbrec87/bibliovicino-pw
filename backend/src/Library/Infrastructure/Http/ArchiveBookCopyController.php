<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Http;

use App\Identity\Application\PublicApi\CurrentUserId;
use App\Library\Application\ArchiveBookCopy\ArchiveBookCopyCommand;
use App\Library\Application\ArchiveBookCopy\ArchiveBookCopyHandler;
use App\Shared\Infrastructure\Http\ApiResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class ArchiveBookCopyController
{
    public function __construct(private ArchiveBookCopyHandler $handler, private CurrentUserId $currentUserId)
    {
    }

    #[Route('/api/library/book-copies/{id}/archive', name: 'api_library_book_copy_archive', methods: ['POST'])]
    public function __invoke(string $id): JsonResponse
    {
        $ownerId = $this->currentUserId->get();
        if (null === $ownerId) {
            throw new AccessDeniedException();
        }
        $this->handler->handle(new ArchiveBookCopyCommand($ownerId, $id));
        return ApiResponse::success([], 204, ['Cache-Control' => 'private, no-store']);
    }
}
