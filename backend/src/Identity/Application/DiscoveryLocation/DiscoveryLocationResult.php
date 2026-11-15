<?php

declare(strict_types=1);

namespace App\Identity\Application\DiscoveryLocation;

use App\Identity\Domain\DiscoveryLocation\DiscoveryLocation;

final readonly class DiscoveryLocationResult
{
    private function __construct(
        public ?float $latitude,
        public ?float $longitude,
    ) {
    }

    public static function fromLocation(?DiscoveryLocation $location): self
    {
        if (null === $location) {
            return new self(null, null);
        }

        return new self($location->latitude(), $location->longitude());
    }

    public function isConfigured(): bool
    {
        return null !== $this->latitude && null !== $this->longitude;
    }
}
