<?php

declare(strict_types=1);

namespace App\Loan\Domain;

use DateTimeImmutable;
use DomainException;

final class LoanRequest
{
    private function __construct(
        private string $id,
        private string $bookCopyId,
        private string $requesterId,
        private LoanRequestStatus $status,
        private DateTimeImmutable $requestedAt,
        private DateTimeImmutable $updatedAt,
    ) {
    }

    public static function request(string $id, string $copyId, string $requesterId, DateTimeImmutable $now): self
    {
        return new self($id, $copyId, $requesterId, LoanRequestStatus::PENDING, $now, $now);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function bookCopyId(): string
    {
        return $this->bookCopyId;
    }

    public function requesterId(): string
    {
        return $this->requesterId;
    }

    public function status(): LoanRequestStatus
    {
        return $this->status;
    }

    public function accept(DateTimeImmutable $now): void
    {
        $this->change(LoanRequestStatus::PENDING, LoanRequestStatus::ACCEPTED, $now);
    }

    public function reject(DateTimeImmutable $now): void
    {
        $this->change(LoanRequestStatus::PENDING, LoanRequestStatus::REJECTED, $now);
    }

    public function lend(DateTimeImmutable $now): void
    {
        $this->change(LoanRequestStatus::ACCEPTED, LoanRequestStatus::LENT, $now);
    }

    private function change(LoanRequestStatus $from, LoanRequestStatus $to, DateTimeImmutable $now): void
    {
        if ($this->status !== $from) {
            throw new DomainException('Loan request transition is unavailable.');
        }
        $this->status = $to;
        $this->updatedAt = $now;
    }

    public function return(DateTimeImmutable $now): void
    {
        $this->change(LoanRequestStatus::LENT, LoanRequestStatus::RETURNED, $now);
    }

    public function cancel(DateTimeImmutable $now): void
    {
        if (!in_array($this->status, [LoanRequestStatus::PENDING, LoanRequestStatus::ACCEPTED], true)) {
            throw new DomainException('Loan request cannot be cancelled.');
        }
        $this->status = LoanRequestStatus::CANCELLED;
        $this->updatedAt = $now;
    }


}
