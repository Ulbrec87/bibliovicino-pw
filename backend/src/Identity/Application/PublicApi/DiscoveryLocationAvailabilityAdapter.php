<?php

declare(strict_types=1);

namespace App\Identity\Application\PublicApi;

use App\Identity\Application\GetDiscoveryLocation\GetDiscoveryLocationCommand;
use App\Identity\Application\GetDiscoveryLocation\GetDiscoveryLocationHandler;
use App\Identity\Domain\User\UserId;

final readonly class DiscoveryLocationAvailabilityAdapter implements DiscoveryLocationAvailability
{
    public function __construct(private GetDiscoveryLocationHandler $getDiscoveryLocation)
    {
    }

    public function isConfigured(string $userId): bool
    {
        return $this->getDiscoveryLocation->handle(new GetDiscoveryLocationCommand(UserId::fromString($userId)))->isConfigured();
    }
}
