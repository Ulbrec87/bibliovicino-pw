<?php

declare(strict_types=1);

namespace App\Identity\Application\RegisterUser;

final readonly class RegisterUserCommand
{
    public function __construct(
        public string $email,
        public string $password,
        public string $displayName,
    ) {
    }
}
