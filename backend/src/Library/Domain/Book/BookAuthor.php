<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use App\Library\Domain\Author\Author;
use InvalidArgumentException;

final class BookAuthor
{
    private function __construct(
        private Book $book,
        private Author $author,
        private int $position,
    ) {
        if ($position < 0 || $position > 9) {
            throw new InvalidArgumentException('An author position must be between 0 and 9.');
        }
    }

    public static function associate(Book $book, Author $author, int $position): self
    {
        return new self($book, $author, $position);
    }

    public function book(): Book
    {
        return $this->book;
    }

    public function author(): Author
    {
        return $this->author;
    }

    public function position(): int
    {
        return $this->position;
    }
}
