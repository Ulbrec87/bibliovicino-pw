<?php

declare(strict_types=1);

namespace App\Loan\Application\ChangeLoanRequest;

use App\Library\Application\PublicApi\LibraryForLoan;
use App\Loan\Domain\LoanRequest;
use App\Loan\Domain\LoanRequestRepository;
use App\Loan\Domain\LoanRequestStatus;
use App\Shared\Application\AtomicOperation;
use App\Shared\Application\Clock;
use DomainException;

final readonly class ChangeLoanRequestHandler
{
    public function __construct(
        private LoanRequestRepository $requests,
        private LibraryForLoan $library,
        private AtomicOperation $atomic,
        private Clock $clock,
    ) {
    }

    public function handle(ChangeLoanRequestCommand $command): void
    {
        $this->atomic->run(function () use ($command): void {
            // Carichiamo lo stato della richiesta dopo il lock sulla copia
            $copyId = $this->requests->copyIdOf($command->requestId);
            if (null === $copyId) {
                throw new LoanRequestNotFound();
            }
            $copy = $this->library->getForLoanRequestWithLock($copyId);
            if (null === $copy) {
                throw new LoanRequestNotFound();
            }
            $request = $this->requests->findById($command->requestId);
            if (null === $request) {
                throw new LoanRequestNotFound();
            }
            $isOwner = $copy->ownerId === $command->actorId;
            $isRequester = $request->requesterId() === $command->actorId;
            if (!$isOwner && !$isRequester) {
                throw new LoanRequestNotFound();
            }

            $now = $this->clock->now();
            switch ($command->action) {
                case 'accept':
                    if (!$isOwner) {
                        throw new LoanActionForbidden();
                    }
                    if (!$copy->eligible) {
                        throw new DomainException('Book copy cannot be accepted.');
                    }
                    $request->accept($now);
                    break;
                case 'reject':
                    if (!$isOwner) {
                        throw new LoanActionForbidden();
                    }
                    $request->reject($now);
                    break;
                case 'cancel':
                    if (!$isRequester && !($isOwner && $request->status() === LoanRequestStatus::ACCEPTED)) {
                        throw new LoanActionForbidden();
                    }
                    $request->cancel($now);
                    break;
                case 'lend':
                    if (!$isOwner) {
                        throw new LoanActionForbidden();
                    }
                    $request->lend($now);
                    $this->library->markLent($copyId, $now);
                    break;
                case 'return':
                    if (!$isOwner) {
                        throw new LoanActionForbidden();
                    }
                    $request->return($now);
                    $this->library->markReturned($copyId, $now);
                    break;
                default:
                    throw new DomainException('Unknown loan action.');
            }
            $this->requests->save($request);
        });
    }
}
