<?php

declare(strict_types=1);

namespace App\Library\Domain\BookCopy;

use App\Library\Domain\Book\Book;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

final class BookCopy
{
    private BookCopyCover $cover;
    private AvailabilityStatus $availabilityStatus;
    private bool $offeredForLoan;
    private bool $isPublished;
    private ?DateTimeImmutable $archivedAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        private BookCopyId $id,
        private Book $book,
        private OwnerId $ownerId,
        private ?string $notes,
        private DateTimeImmutable $createdAt,
    ) {
        self::assertValidNotes($notes);
        $this->cover = BookCopyCover::none();
        $this->availabilityStatus = AvailabilityStatus::AVAILABLE;
        $this->offeredForLoan = false;
        $this->isPublished = false;
        $this->archivedAt = null;
        $this->updatedAt = $createdAt;
    }

    public static function create(
        BookCopyId $id,
        Book $book,
        OwnerId $ownerId,
        ?string $notes,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            $id,
            $book,
            $ownerId,
            $notes,
            $createdAt,
        );
    }

    public function cover(): BookCopyCover
    {
        return $this->cover;
    }

    public function changeCover(BookCopyCover $cover, DateTimeImmutable $now): void
    {
        $this->assertNotArchived();
        if ($this->cover == $cover) {
            return;
        }
        $this->cover = $cover;
        $this->updatedAt = $now;
    }

    public function id(): BookCopyId
    {
        return $this->id;
    }

    public function book(): Book
    {
        return $this->book;
    }

    public function ownerId(): OwnerId
    {
        return $this->ownerId;
    }

    public function availabilityStatus(): AvailabilityStatus
    {
        return $this->availabilityStatus;
    }

    public function offeredForLoan(): bool
    {
        return $this->offeredForLoan;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function archivedAt(): ?DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function changeDetails(
        ?string $notes,
        DateTimeImmutable $now,
    ): void {
        $this->assertNotArchived();
        self::assertValidNotes($notes);

        if ($this->notes === $notes) {
            return;
        }

        $this->notes = $notes;
        $this->updatedAt = $now;
    }

    public function changeLoanOffer(
        bool $offeredForLoan,
        DateTimeImmutable $now,
    ): void {
        $this->assertNotArchived();

        if ($this->isPublished && !$offeredForLoan) {
            throw new DomainException('A published BookCopy must enable a loan offer.');
        }

        if ($this->offeredForLoan === $offeredForLoan) {
            return;
        }

        $this->offeredForLoan = $offeredForLoan;
        $this->updatedAt = $now;
    }

    public function publish(DateTimeImmutable $now): void
    {
        $this->assertNotArchived();

        if (!$this->offeredForLoan) {
            throw new DomainException('A BookCopy requires a loan offer before publication.');
        }

        if ($this->isPublished) {
            return;
        }

        $this->isPublished = true;
        $this->updatedAt = $now;
    }

    public function unpublish(DateTimeImmutable $now): void
    {
        $this->assertNotArchived();

        if (!$this->isPublished) {
            return;
        }

        $this->isPublished = false;
        $this->updatedAt = $now;
    }

    public function makeAvailable(DateTimeImmutable $now): void
    {
        $this->assertNotArchived();

        if (AvailabilityStatus::AVAILABLE === $this->availabilityStatus) {
            return;
        }

        $this->availabilityStatus = AvailabilityStatus::AVAILABLE;
        $this->updatedAt = $now;
    }

    public function makeUnavailable(DateTimeImmutable $now): void
    {
        $this->assertNotArchived();

        if (AvailabilityStatus::UNAVAILABLE === $this->availabilityStatus) {
            return;
        }

        $this->availabilityStatus = AvailabilityStatus::UNAVAILABLE;
        $this->updatedAt = $now;
    }

    public function archive(DateTimeImmutable $now): void
    {
        if (null !== $this->archivedAt) {
            return;
        }

        $this->archivedAt = $now;
        $this->isPublished = false;
        $this->updatedAt = $now;
    }

    // Check sulla copia, effettua controlli per verificare se è disponibile al prestito.
    public function isEligibleForLoanRequest(): bool
    {
        return null === $this->archivedAt
            && $this->isPublished
            && AvailabilityStatus::AVAILABLE === $this->availabilityStatus
            && $this->offeredForLoan;
    }

    private static function assertValidNotes(?string $notes): void
    {
        if (null !== $notes && mb_strlen($notes) > 1100) {
            throw new InvalidArgumentException('BookCopy notes cannot exceed 1100 characters.');
        }
    }

    private function assertNotArchived(): void
    {
        if (null !== $this->archivedAt) {
            throw new DomainException('An archived BookCopy cannot be modified.');
        }
    }
}
