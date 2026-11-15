<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Geolocation;

use App\Identity\Application\SetDiscoveryLocation\DiscoveryLocationApproximator;
use App\Identity\Domain\DiscoveryLocation\DiscoveryLocation;
use Doctrine\DBAL\Connection;
use LogicException;
use PDO;

final readonly class PostgisDiscoveryLocationApproximator implements DiscoveryLocationApproximator
{
    private const float CELL_SIZE_METRES = 1000.0;
    private const float CELL_CENTRE_OFFSET_METRES = 500.0;

    //UTM 33N (EPSG:32633) permette di calcolare la griglia in metri ed è adatto ad un'area urbana.
    private const string PROJECT_TO_UTM_SQL = <<<'SQL'
        SELECT
            ST_X(utm_point.point) AS easting,
            ST_Y(utm_point.point) AS northing
        FROM (
            SELECT ST_Transform(
                ST_SetSRID(ST_MakePoint(:longitude, :latitude), 4326),
                32633
            ) AS point
        ) AS utm_point
        SQL;

    private const string PROJECT_TO_WGS84_SQL = <<<'SQL'
        SELECT
            ST_Y(wgs84_point.point) AS latitude,
            ST_X(wgs84_point.point) AS longitude
        FROM (
            SELECT ST_Transform(
                ST_SetSRID(ST_MakePoint(:easting, :northing), 32633),
                4326
            ) AS point
        ) AS wgs84_point
        SQL;

    public function __construct(private Connection $connection)
    {
    }

    public function approximate(float $latitude, float $longitude): DiscoveryLocation
    {
        $pdo = $this->nativeConnection();
        $projectedPoint = $this->projectToUtm($pdo, $latitude, $longitude);

        $centreEasting = floor($projectedPoint['easting'] / self::CELL_SIZE_METRES)
            * self::CELL_SIZE_METRES
            + self::CELL_CENTRE_OFFSET_METRES;
        $centreNorthing = floor($projectedPoint['northing'] / self::CELL_SIZE_METRES)
            * self::CELL_SIZE_METRES
            + self::CELL_CENTRE_OFFSET_METRES;

        $representativePoint = $this->projectToWgs84($pdo, $centreEasting, $centreNorthing);

        return DiscoveryLocation::fromRepresentativePoint(
            $representativePoint['latitude'],
            $representativePoint['longitude'],
        );
    }

    private function nativeConnection(): PDO
    {
        $pdo = $this->connection->getNativeConnection();

        if (!$pdo instanceof PDO) {
            throw new LogicException('The PostGIS location approximator requires the PDO PostgreSQL driver.');
        }

        return $pdo;
    }

    /** @return array{easting: float, northing: float} */
    private function projectToUtm(PDO $pdo, float $latitude, float $longitude): array
    {
        $statement = $pdo->prepare(self::PROJECT_TO_UTM_SQL);
        $statement->execute([
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row) || !is_numeric($row['easting'] ?? null) || !is_numeric($row['northing'] ?? null)) {
            throw new LogicException('PostGIS could not project the discovery location to UTM.');
        }

        return [
            'easting' => (float) $row['easting'],
            'northing' => (float) $row['northing'],
        ];
    }

    /** @return array{latitude: float, longitude: float} */
    private function projectToWgs84(PDO $pdo, float $easting, float $northing): array
    {
        $statement = $pdo->prepare(self::PROJECT_TO_WGS84_SQL);
        $statement->execute([
            'easting' => $easting,
            'northing' => $northing,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row) || !is_numeric($row['latitude'] ?? null) || !is_numeric($row['longitude'] ?? null)) {
            throw new LogicException('PostGIS could not project the representative discovery location to WGS84.');
        }

        return [
            'latitude' => (float) $row['latitude'],
            'longitude' => (float) $row['longitude'],
        ];
    }
}
