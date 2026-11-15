<?php

declare(strict_types=1);

namespace App\Loan\Application\CreateLoanRequest;

final readonly class CreateLoanRequestCommand
{
    public function __construct(
        public string $requesterId,
        public string $bookCopyId,
    ) {
    }
}
