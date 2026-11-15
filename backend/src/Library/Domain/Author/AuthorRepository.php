<?php

declare(strict_types=1);

namespace App\Library\Domain\Author;

interface AuthorRepository
{
    public function nextId(): AuthorId;

    public function findById(AuthorId $id): ?Author;

    public function findByNameCaseInsensitive(string $name): ?Author;

    public function save(Author $author): void;
}
