<?php

declare(strict_types=1);

namespace App\ReadModel\AdminStatistics\Application;

use DateTimeImmutable;

interface AdminStatisticsStore {
    public function forPeriod(DateTimeImmutable $start, DateTimeImmutable $end): AdminStatisticsResult;
}
