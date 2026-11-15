<?php

declare(strict_types=1);

namespace App\Identity\Domain\DiscoveryLocation;

use InvalidArgumentException;

final readonly class DiscoveryLocation
{
    private function __construct(
        private float $latitude,
        private float $longitude,
    ) {
    }

    public static function fromRepresentativePoint(float $latitude, float $longitude): self
    {
        if (!is_finite($latitude) || $latitude < -90.0 || $latitude > 90.0) {
            throw new InvalidArgumentException('DiscoveryLocation latitude must be a finite WGS84 value.');
        }

        if (!is_finite($longitude) || $longitude < -180.0 || $longitude > 180.0) {
            throw new InvalidArgumentException('DiscoveryLocation longitude must be a finite WGS84 value.');
        }

        return new self($latitude, $longitude);
    }

    public function latitude(): float
    {
        return $this->latitude;
    }

    public function longitude(): float
    {
        return $this->longitude;
    }

    public function equals(self $other): bool
    {
        return $this->latitude === $other->latitude
            && $this->longitude === $other->longitude;
    }
}
