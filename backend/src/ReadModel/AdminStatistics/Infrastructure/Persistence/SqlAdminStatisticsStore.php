<?php

declare(strict_types=1);

namespace App\ReadModel\AdminStatistics\Infrastructure\Persistence;

use App\ReadModel\AdminStatistics\Application\AdminStatisticsResult;
use App\ReadModel\AdminStatistics\Application\AdminStatisticsStore;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class SqlAdminStatisticsStore implements AdminStatisticsStore
{
    public function __construct(private Connection $connection)
    {
    }

    public function forPeriod(DateTimeImmutable $start, DateTimeImmutable $end): AdminStatisticsResult
    {
        $totals = $this->connection->fetchAssociative(<<<'SQL'
            SELECT
                (SELECT COUNT(*) FROM identity_user) AS registered_users,
                (SELECT COUNT(*) FROM library_book_copy WHERE is_published = TRUE AND archived_at IS NULL) AS published_copies,
                (SELECT COUNT(*) FROM loan_request) AS loan_requests,
                (SELECT COUNT(*) FROM loan_request WHERE status = 'RETURNED') AS completed_loans
            SQL);

        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT date_trunc('week', requested_at)::date AS week_start, COUNT(*) AS request_count
            FROM loan_request
            WHERE requested_at >= CAST(:periodStart AS timestamp without time zone)
              AND requested_at < CAST(:periodEnd AS timestamp without time zone)
            GROUP BY week_start
            ORDER BY week_start
            SQL, [
                'periodStart' => $start->format('Y-m-d H:i:s'),
                'periodEnd' => $end->format('Y-m-d H:i:s'),
            ]);

        $countsByWeek = [];
        foreach ($rows as $row) {
            $countsByWeek[(string) $row['week_start']] = (int) $row['request_count'];
        }

        $weekly = [];
        for ($weekStart = $start; $weekStart < $end; $weekStart = $weekStart->modify('+1 week')) {
            $weekDate = $weekStart->format('Y-m-d');
            $weekly[] = ['weekStart' => $weekDate, 'count' => $countsByWeek[$weekDate] ?? 0];
        }

        return new AdminStatisticsResult(
            (int) $totals['registered_users'],
            (int) $totals['published_copies'],
            (int) $totals['loan_requests'],
            (int) $totals['completed_loans'],
            $weekly,
        );
    }
}
