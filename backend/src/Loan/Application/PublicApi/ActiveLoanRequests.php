<?php

declare(strict_types=1);

namespace App\Loan\Application\PublicApi;

interface ActiveLoanRequests
{
    public function hasActiveForCopy(string $copyId): bool;
}
