<?php

declare(strict_types=1);

namespace App\ReadModel\LoanRequests\Infrastructure\Http;

use App\Identity\Application\PublicApi\CurrentUserId;
use App\ReadModel\LoanRequests\Application\LoanRequestsStore;
use App\Shared\Infrastructure\Http\ApiResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class ListLoanRequestsController
{
    public function __construct(private LoanRequestsStore $store, private CurrentUserId $currentUserId) {}

    #[Route('/api/loan/requests', name: 'api_loan_requests', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $viewerId = $this->currentUserId->get();
        if (null === $viewerId) throw new AccessDeniedException();
        return ApiResponse::success(
            ['requests' => $this->store->forParticipant($viewerId)],
            headers: ['Cache-Control' => 'private, no-store'],
        );
    }
}
