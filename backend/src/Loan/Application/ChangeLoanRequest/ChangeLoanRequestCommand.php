<?php

declare(strict_types=1);

namespace App\Loan\Application\ChangeLoanRequest;

final readonly class ChangeLoanRequestCommand
{
    public function __construct(
        public string $requestId,
        public string $actorId,
        public string $action,
    ) {
    }
}
