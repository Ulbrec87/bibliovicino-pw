<?php

declare(strict_types=1);

namespace App\Library\Domain\Author;

use DateTimeImmutable;
use InvalidArgumentException;

final class Author
{
    private function __construct(
        private AuthorId $id,
        private string $name,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
        $this->name = self::normaliseName($name);
    }

    public static function create(AuthorId $id, string $name, DateTimeImmutable $createdAt): self
    {
        return new self($id, $name, $createdAt, $createdAt);
    }

    public static function normaliseName(string $name): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));
        $length = mb_strlen($name);

        if ($length < 1 || $length > 255) {
            throw new InvalidArgumentException('An Author name must contain between 1 and 255 characters.');
        }

        return $name;
    }

    public function id(): AuthorId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
