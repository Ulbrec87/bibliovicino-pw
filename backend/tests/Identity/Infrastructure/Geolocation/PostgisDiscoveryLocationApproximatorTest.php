<?php

declare(strict_types=1);

namespace App\Tests\Identity\Infrastructure\Geolocation;

use App\Identity\Application\SetDiscoveryLocation\DiscoveryLocationApproximator;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PostgisDiscoveryLocationApproximatorTest extends KernelTestCase
{
    private DiscoveryLocationApproximator $approximator;
    private Connection $connection;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->approximator = self::getContainer()->get(DiscoveryLocationApproximator::class);
        $this->connection = self::getContainer()->get(Connection::class);
    }

    public function testLocationCellsAreOneKilometreApart(): void
    {
        [$firstLatitude, $firstLongitude] = $this->wgs84FromUtm(291100.0, 4641100.0);
        [$eastLatitude, $eastLongitude] = $this->wgs84FromUtm(292100.0, 4641100.0);
        [$northLatitude, $northLongitude] = $this->wgs84FromUtm(291100.0, 4642100.0);
        $first = $this->approximator->approximate($firstLatitude, $firstLongitude);
        $east = $this->approximator->approximate($eastLatitude, $eastLongitude);
        $north = $this->approximator->approximate($northLatitude, $northLongitude);

        $distances = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT ST_Distance(
                    ST_Transform(ST_SetSRID(ST_MakePoint(:firstLongitude, :firstLatitude), 4326), 32633),
                    ST_Transform(ST_SetSRID(ST_MakePoint(:eastLongitude, :eastLatitude), 4326), 32633)
                ) AS east,
                ST_Distance(
                    ST_Transform(ST_SetSRID(ST_MakePoint(:firstLongitude, :firstLatitude), 4326), 32633),
                    ST_Transform(ST_SetSRID(ST_MakePoint(:northLongitude, :northLatitude), 4326), 32633)
                ) AS north
                SQL,
            [
                'firstLatitude' => $first->latitude(),
                'firstLongitude' => $first->longitude(),
                'eastLatitude' => $east->latitude(),
                'eastLongitude' => $east->longitude(),
                'northLatitude' => $north->latitude(),
                'northLongitude' => $north->longitude(),
            ],
        );

        self::assertIsArray($distances);
        self::assertEqualsWithDelta(1000.0, (float) $distances['east'], 0.001);
        self::assertEqualsWithDelta(1000.0, (float) $distances['north'], 0.001);
    }

    /** @return array{float, float} */
    private function wgs84FromUtm(float $easting, float $northing): array
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT ST_Y(point) AS latitude, ST_X(point) AS longitude
                FROM (
                    SELECT ST_Transform(
                        ST_SetSRID(ST_MakePoint(:easting, :northing), 32633),
                        4326
                    ) AS point
                ) transformed
                SQL,
            ['easting' => $easting, 'northing' => $northing],
        );

        self::assertIsArray($row);

        return [(float) $row['latitude'], (float) $row['longitude']];
    }
}
