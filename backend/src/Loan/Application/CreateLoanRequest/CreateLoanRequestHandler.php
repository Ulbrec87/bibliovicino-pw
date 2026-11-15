<?php

declare(strict_types=1);

namespace App\Loan\Application\CreateLoanRequest;

use App\Library\Application\PublicApi\LibraryForLoan;
use App\Loan\Domain\LoanRequest;
use App\Loan\Domain\LoanRequestRepository;
use App\Shared\Application\AtomicOperation;
use App\Shared\Application\Clock;
use DomainException;

final readonly class CreateLoanRequestHandler
{
    public function __construct(
        private LoanRequestRepository $requests,
        private LibraryForLoan $library,
        private AtomicOperation $atomic,
        private Clock $clock,
    ) {
    }

    public function handle(CreateLoanRequestCommand $command): string
    {
        return $this->atomic->run(function () use ($command): string {
            $copy = $this->library->getForLoanRequestWithLock($command->bookCopyId);
            if (null === $copy || !$copy->publiclyVisible) {
                throw new LoanTargetNotFound();
            }
            if ($copy->ownerId === $command->requesterId) {
                throw new DomainException('Cannot request your own book copy.');
            }
            if (!$copy->eligible) {
                throw new DomainException('Book copy cannot receive a loan request.');
            }
            if ($this->requests->hasActiveForCopy($copy->id)) {
                throw new DomainException('Book copy already has an active request.');
            }
            $request = LoanRequest::request($this->requests->nextId(), $copy->id, $command->requesterId, $this->clock->now());
            $this->requests->save($request);
            return $request->id();
        });
    }
}
