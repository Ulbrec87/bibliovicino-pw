<?php

declare(strict_types=1);

namespace App\Identity\Application\RegisterUser;

interface PasswordHasher
{
    public function hash(string $plainPassword): string;
}
