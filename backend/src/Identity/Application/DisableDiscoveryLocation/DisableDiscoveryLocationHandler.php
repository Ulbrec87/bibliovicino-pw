<?php

declare(strict_types=1);

namespace App\Identity\Application\DisableDiscoveryLocation;

use App\Identity\Domain\User\UserRepository;
use LogicException;

final readonly class DisableDiscoveryLocationHandler
{
    public function __construct(private UserRepository $users)
    {
    }

    public function handle(DisableDiscoveryLocationCommand $command): void
    {
        $user = $this->users->findById($command->userId);

        if (null === $user) {
            throw new LogicException('The authenticated user could not be loaded.');
        }

        $user->disableDiscoveryLocation();
        $this->users->save($user);
    }
}
