<?php

declare(strict_types=1);

namespace App\Identity\Application\SetDiscoveryLocation;

use App\Identity\Domain\DiscoveryLocation\DiscoveryLocation;

interface DiscoveryLocationApproximator
{
    public function approximate(float $latitude, float $longitude): DiscoveryLocation;
}
