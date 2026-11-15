<?php

declare(strict_types=1);

namespace App\Library\Domain\BookCopy;

interface BookCopyRepository
{
    public function nextId(): BookCopyId;

    public function findById(BookCopyId $id): ?BookCopy;

    public function save(BookCopy $copy): void;
}
