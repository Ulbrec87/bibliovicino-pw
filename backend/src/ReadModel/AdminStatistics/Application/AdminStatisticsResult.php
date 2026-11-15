<?php

declare(strict_types=1);

namespace App\ReadModel\AdminStatistics\Application;

final readonly class AdminStatisticsResult
{
    /** @param list<array{weekStart: string, count: int}> $weeklyLoanRequests */
    public function __construct(
        public int $registeredUsers,
        public int $publishedCopies,
        public int $loanRequests,
        public int $completedLoans,
        public array $weeklyLoanRequests,
    ) {
    }
}
