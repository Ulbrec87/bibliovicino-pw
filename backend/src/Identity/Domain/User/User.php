<?php

declare(strict_types=1);

namespace App\Identity\Domain\User;

use App\Identity\Domain\DiscoveryLocation\DiscoveryLocation;
use DateTimeImmutable;

final class User
{
    /** @param list<string> $roles */
    private function __construct(
        private UserId $id,
        private string $email,
        private string $passwordHash,
        private string $displayName,
        private DateTimeImmutable $createdAt,
        private array $roles = ['ROLE_USER'],
        private ?DiscoveryLocation $discoveryLocation = null,
    ) {
    }

    public static function register(
        UserId $id,
        string $email,
        string $passwordHash,
        string $displayName,
        DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $email, $passwordHash, self::normaliseDisplayName($displayName), $createdAt);
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    public function changeDisplayName(string $displayName): void
    {
        $this->displayName = self::normaliseDisplayName($displayName);
    }

    private static function normaliseDisplayName(string $displayName): string
    {
        $displayName = mb_trim($displayName);
        $length = mb_strlen($displayName);

        if ($length < 1 || $length > 80) {
            throw new InvalidDisplayName();
        }

        return $displayName;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return list<string> */
    public function roles(): array
    {
        return $this->roles;
    }

    public function discoveryLocation(): ?DiscoveryLocation
    {
        return $this->discoveryLocation;
    }

    public function setDiscoveryLocation(DiscoveryLocation $discoveryLocation): void
    {
        $this->discoveryLocation = $discoveryLocation;
    }

    public function disableDiscoveryLocation(): void
    {
        $this->discoveryLocation = null;
    }
}
