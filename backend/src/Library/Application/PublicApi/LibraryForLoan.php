<?php

declare(strict_types=1);

namespace App\Library\Application\PublicApi;

use DateTimeImmutable;

interface LibraryForLoan
{
    public function getForLoanRequestWithLock(string $copyId): ?LoanCopyInfo;
    public function markLent(string $copyId, DateTimeImmutable $now): void;
    public function markReturned(string $copyId, DateTimeImmutable $now): void;
}
