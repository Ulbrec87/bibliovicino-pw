<?php

declare(strict_types=1);

namespace App\Loan\Infrastructure\Http;

use App\Identity\Application\PublicApi\CurrentUserId;
use App\Loan\Application\CreateLoanRequest\CreateLoanRequestCommand;
use App\Loan\Application\CreateLoanRequest\CreateLoanRequestHandler;
use App\Shared\Infrastructure\Http\ApiResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

final readonly class CreateLoanRequestController
{
    public function __construct(
        private CreateLoanRequestHandler $handler,
        private CurrentUserId $currentUserId,
    ) {
    }

    #[Route('/api/loan/requests', name: 'api_loan_request_create', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $actorId = $this->currentUserId->get();
        if (null === $actorId) {
            throw new AccessDeniedException();
        }
        $input = json_decode($request->getContent(), true);
        if (!is_array($input) || !is_string($input['bookCopyId'] ?? null) || !Uuid::isValid($input['bookCopyId'])) {
            return ApiResponse::validationError(['bookCopyId' => ['Seleziona una copia valida.']]);
        }
        $id = $this->handler->handle(new CreateLoanRequestCommand($actorId, $input['bookCopyId']));
        return ApiResponse::success(['id' => $id], 201, ['Cache-Control' => 'private, no-store']);
    }
}
