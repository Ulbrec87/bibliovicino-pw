<?php

declare(strict_types=1);

namespace App\Library\Application\PublishBookCopy;

use App\Identity\Application\PublicApi\DiscoveryLocationAvailability;
use App\Library\Application\CreateBookCopy\DiscoveryLocationRequired;
use App\Library\Application\BookCopyManagement\OwnedBookCopyService;
use App\Library\Domain\BookCopy\BookCopyRepository;
use App\Shared\Application\Clock;
use App\Shared\Application\AtomicOperation;
use App\Library\Application\BookCopyManagement\BookCopyLock;
use App\Loan\Application\PublicApi\ActiveLoanRequests;
use DomainException;

final readonly class PublishBookCopyHandler
{
    public function __construct(
        private OwnedBookCopyService $ownedCopy,
        private BookCopyRepository $copies,
        private Clock $clock,
        private DiscoveryLocationAvailability $discoveryLocation,
        private AtomicOperation $atomic,
        private BookCopyLock $lock,
        private ActiveLoanRequests $loans,
    ) {
    }

    public function handle(PublishBookCopyCommand $command): void
    {
        $this->atomic->run(function () use ($command): void {
            $this->lock->lock($command->bookCopyId);
            $copy = $this->ownedCopy->get($command->bookCopyId, $command->ownerId);
            if ($this->loans->hasActiveForCopy($command->bookCopyId)) throw new DomainException('Copy has an active loan request.');
            if (!$this->discoveryLocation->isConfigured($command->ownerId)) throw new DiscoveryLocationRequired();
            $now = $this->clock->now();
            $copy->changeLoanOffer(true, $now);
            $copy->publish($now);
            $this->copies->save($copy);
        });
    }
}
