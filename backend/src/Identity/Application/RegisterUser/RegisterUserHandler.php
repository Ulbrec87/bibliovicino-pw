<?php

declare(strict_types=1);

namespace App\Identity\Application\RegisterUser;

use App\Identity\Domain\User\User;
use App\Identity\Domain\User\UserRepository;
use App\Shared\Application\Clock;

final readonly class RegisterUserHandler
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwordHasher,
        private Clock $clock,
    ) {
    }

    public function handle(RegisterUserCommand $command): User
    {
        $email = mb_strtolower(trim($command->email));

        if (null !== $this->users->findByEmail($email)) {
            throw new DuplicateEmail();
        }

        $user = User::register(
            $this->users->nextId(),
            $email,
            $this->passwordHasher->hash($command->password),
            $command->displayName,
            $this->clock->now(),
        );

        $this->users->save($user);

        return $user;
    }
}
