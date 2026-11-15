<?php

declare(strict_types=1);

namespace App\ReadModel\LoanRequests\Application;

interface LoanRequestsStore
{
    public function forParticipant(string $viewerId): LoanRequestsResult;
}
