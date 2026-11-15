<?php

declare(strict_types=1);

namespace App\Loan\Infrastructure\Persistence;

use App\Loan\Application\PublicApi\ActiveLoanRequests;
use App\Loan\Domain\LoanRequest;
use App\Loan\Domain\LoanRequestRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineLoanRequestRepository implements LoanRequestRepository, ActiveLoanRequests
{
    public function __construct(
        private ManagerRegistry $registry,
        private Connection $connection,
    ) {
    }

    public function nextId(): string
    {
        return Uuid::v7()->toRfc4122();
    }

    public function copyIdOf(string $requestId): ?string
    {
        if (!Uuid::isValid($requestId)) {
            return null;
        }
        $value = $this->connection->fetchOne('SELECT book_copy_id::text FROM loan_request WHERE id = :id', ['id' => $requestId]);
        return false === $value ? null : (string) $value;
    }

    public function findById(string $requestId): ?LoanRequest
    {
        if (!Uuid::isValid($requestId)) {
            return null;
        }
        return $this->registry->getManager()->find(LoanRequest::class, $requestId);
    }

    public function hasActiveForCopy(string $copyId): bool
    {
        return false !== $this->connection->fetchOne(
            "SELECT 1 FROM loan_request WHERE book_copy_id = :id AND status IN ('PENDING', 'ACCEPTED', 'LENT')",
            ['id' => $copyId],
        );
    }

    public function save(LoanRequest $request): void
    {
        $this->registry->getManager()->persist($request);
        $this->registry->getManager()->flush();
    }
}
