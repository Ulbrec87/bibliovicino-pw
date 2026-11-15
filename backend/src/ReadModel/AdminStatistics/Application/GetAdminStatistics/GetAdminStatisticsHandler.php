<?php

declare(strict_types=1);

namespace App\ReadModel\AdminStatistics\Application\GetAdminStatistics;

use App\ReadModel\AdminStatistics\Application\AdminStatisticsResult;
use App\ReadModel\AdminStatistics\Application\AdminStatisticsStore;
use App\Shared\Application\Clock;
use DateTimeZone;

final readonly class GetAdminStatisticsHandler
{
    public function __construct(
        private AdminStatisticsStore $store,
        private Clock $clock,
    ) {
    }

    public function handle(): AdminStatisticsResult
    {
        $utcNow = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
        $currentWeekStart = $utcNow->modify('monday this week')->setTime(0, 0);
        return $this->store->forPeriod($currentWeekStart->modify('-7 weeks'), $currentWeekStart->modify('+1 week'));
    }
}
