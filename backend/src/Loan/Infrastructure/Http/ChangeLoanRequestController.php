<?php

declare(strict_types=1);

namespace App\Loan\Infrastructure\Http;

use App\Identity\Application\PublicApi\CurrentUserId;
use App\Loan\Application\ChangeLoanRequest\ChangeLoanRequestCommand;
use App\Loan\Application\ChangeLoanRequest\ChangeLoanRequestHandler;
use App\Shared\Infrastructure\Http\ApiResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class ChangeLoanRequestController
{
    public function __construct(
        private ChangeLoanRequestHandler $handler,
        private CurrentUserId $currentUserId,
    ) {
    }

    #[Route('/api/loan/requests/{id}/{action}', name: 'api_loan_request_change', requirements: ['action' => 'accept|reject|cancel|lend|return'], methods: ['POST'])]
    public function __invoke(string $id, string $action): JsonResponse
    {
        $actorId = $this->currentUserId->get();
        if (null === $actorId) {
            throw new AccessDeniedException();
        }
        $this->handler->handle(new ChangeLoanRequestCommand($id, $actorId, $action));
        return ApiResponse::success([], 204, ['Cache-Control' => 'private, no-store']);
    }
}
