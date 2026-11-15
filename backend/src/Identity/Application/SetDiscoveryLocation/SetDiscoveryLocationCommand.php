<?php

declare(strict_types=1);

namespace App\Identity\Application\SetDiscoveryLocation;

use App\Identity\Domain\User\UserId;

final readonly class SetDiscoveryLocationCommand
{
    public function __construct(
        public UserId $userId,
        public float $latitude,
        public float $longitude,
    ) {
    }
}
