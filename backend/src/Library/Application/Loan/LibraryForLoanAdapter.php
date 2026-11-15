<?php

declare(strict_types=1);

namespace App\Library\Application\Loan;

use App\Library\Application\BookCopyManagement\BookCopyLock;
use App\Library\Application\PublicApi\LibraryForLoan;
use App\Library\Application\PublicApi\LoanCopyInfo;
use App\Library\Domain\BookCopy\AvailabilityStatus;
use App\Library\Domain\BookCopy\BookCopy;
use App\Library\Domain\BookCopy\BookCopyRepository;
use DateTimeImmutable;
use DomainException;

final readonly class LibraryForLoanAdapter implements LibraryForLoan
{
    public function __construct(private BookCopyLock $lock, private BookCopyRepository $copies) {}

    public function getForLoanRequestWithLock(string $copyId): ?LoanCopyInfo
    {
        $copy = $this->lock->lock($copyId);
        return null === $copy ? null : new LoanCopyInfo(
            $copy->id()->toString(),
            $copy->ownerId()->toString(),
            $copy->isEligibleForLoanRequest(),
            $copy->archivedAt() === null && $copy->isPublished(),
            $copy->availabilityStatus()->value,
        );
    }

    public function markLent(string $copyId, DateTimeImmutable $now): void
    {
        $copy = $this->copy($copyId);
        if (!$copy->isEligibleForLoanRequest()) throw new DomainException('Book copy cannot be handed over.');
        $copy->makeUnavailable($now);
        $this->copies->save($copy);
    }

    public function markReturned(string $copyId, DateTimeImmutable $now): void
    {
        $copy = $this->copy($copyId);
        if ($copy->archivedAt() !== null || $copy->availabilityStatus() !== AvailabilityStatus::UNAVAILABLE) {
            throw new DomainException('Book copy cannot be returned.');
        }
        $copy->makeAvailable($now);
        $this->copies->save($copy);
    }

    private function copy(string $id): BookCopy
    {
        return $this->lock->lock($id) ?? throw new DomainException('Book copy unavailable.');
    }
}
