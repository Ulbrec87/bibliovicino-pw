<?php

declare(strict_types=1);

namespace App\Identity\Application\UpdateProfile;

use App\Identity\Domain\User\User;
use App\Identity\Domain\User\UserRepository;
use LogicException;

final readonly class UpdateProfileHandler
{
    public function __construct(private UserRepository $users)
    {
    }

    public function handle(UpdateProfileCommand $command): User
    {
        $user = $this->users->findById($command->userId);

        if (null === $user) {
            throw new LogicException('The authenticated user could not be loaded.');
        }

        $user->changeDisplayName($command->displayName);
        $this->users->save($user);

        return $user;
    }
}
