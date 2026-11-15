<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\User\User;
use App\Identity\Domain\User\UserId;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    /** @param list<string> $roles */
    private function __construct(
        private UserId $id,
        private string $email,
        private string $passwordHash,
        private string $displayName,
        private array $roles,
    ) {
    }

    public static function fromDomain(User $user): self
    {
        return new self(
            $user->id(),
            $user->email(),
            $user->passwordHash(),
            $user->displayName(),
            $user->roles(),
        );
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return $this->roles;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
    }
}
