<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Type;

use App\Identity\Domain\DiscoveryLocation\DiscoveryLocation;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;

final class DiscoveryLocationType extends Type
{
    private const string COORDINATE_PATTERN = '[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[Ee][+-]?\d+)?';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'GEOGRAPHY(POINT, 4326)';
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof DiscoveryLocation) {
            throw new InvalidArgumentException('The discovery_location Doctrine type expects a DiscoveryLocation value.');
        }

        return sprintf(
            'SRID=4326;POINT(%.15F %.15F)',
            $value->longitude(),
            $value->latitude(),
        );
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DiscoveryLocation
    {
        if (null === $value || $value instanceof DiscoveryLocation) {
            return $value;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException('The discovery_location database value must be an EWKT string.');
        }

        $pattern = sprintf(
            '/^SRID=4326;POINT\((%1$s)\s+(%1$s)\)$/D',
            self::COORDINATE_PATTERN,
        );

        if (1 !== preg_match($pattern, trim($value), $matches)) {
            throw new InvalidArgumentException('The discovery_location database value must be a WGS84 point.');
        }

        return DiscoveryLocation::fromRepresentativePoint(
            (float) $matches[2],
            (float) $matches[1],
        );
    }

    public function convertToDatabaseValueSQL(string $sqlExpr, AbstractPlatform $platform): string
    {
        return sprintf('ST_GeogFromText(%s)', $sqlExpr);
    }

    public function convertToPHPValueSQL(string $sqlExpr, AbstractPlatform $platform): string
    {
        return sprintf('ST_AsEWKT((%s)::geometry)', $sqlExpr);
    }
}
