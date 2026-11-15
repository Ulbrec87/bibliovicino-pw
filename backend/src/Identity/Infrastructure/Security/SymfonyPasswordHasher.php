<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\RegisterUser\PasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final readonly class SymfonyPasswordHasher implements PasswordHasher
{
    public function __construct(private PasswordHasherFactoryInterface $passwordHashers)
    {
    }

    public function hash(string $plainPassword): string
    {
        return $this->passwordHashers
            ->getPasswordHasher(SecurityUser::class)
            ->hash($plainPassword);
    }
}
