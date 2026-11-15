<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Http;

use App\Identity\Application\PublicApi\CurrentUserId;
use App\Library\Application\UnpublishBookCopy\UnpublishBookCopyCommand;
use App\Library\Application\UnpublishBookCopy\UnpublishBookCopyHandler;
use App\Shared\Infrastructure\Http\ApiResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class UnpublishBookCopyController
{
    public function __construct(private UnpublishBookCopyHandler $handler, private CurrentUserId $currentUserId)
    {
    }

    #[Route('/api/library/book-copies/{id}/unpublish', name: 'api_library_book_copy_unpublish', methods: ['POST'])]
    public function __invoke(string $id): JsonResponse
    {
        $ownerId = $this->currentUserId->get();
        if (null === $ownerId) {
            throw new AccessDeniedException();
        }
        $this->handler->handle(new UnpublishBookCopyCommand($ownerId, $id));
        return ApiResponse::success([], 204, ['Cache-Control' => 'private, no-store']);
    }
}
