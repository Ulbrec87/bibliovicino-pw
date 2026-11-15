<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

interface BookRepository
{
    public function nextId(): BookId;

    public function findById(BookId $id): ?Book;

    public function findByIsbn(Isbn $isbn): ?Book;

    public function save(Book $book): void;
}
