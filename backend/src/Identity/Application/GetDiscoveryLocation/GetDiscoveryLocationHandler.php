<?php

declare(strict_types=1);

namespace App\Identity\Application\GetDiscoveryLocation;

use App\Identity\Application\DiscoveryLocation\DiscoveryLocationResult;
use App\Identity\Domain\User\UserRepository;
use LogicException;

final readonly class GetDiscoveryLocationHandler
{
    public function __construct(private UserRepository $users)
    {
    }

    public function handle(GetDiscoveryLocationCommand $command): DiscoveryLocationResult
    {
        $user = $this->users->findById($command->userId);

        if (null === $user) {
            throw new LogicException('The authenticated user could not be loaded.');
        }

        return DiscoveryLocationResult::fromLocation($user->discoveryLocation());
    }
}
