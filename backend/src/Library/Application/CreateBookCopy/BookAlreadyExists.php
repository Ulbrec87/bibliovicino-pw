<?php

declare(strict_types=1);

namespace App\Library\Application\CreateBookCopy;

use RuntimeException;

final class BookAlreadyExists extends RuntimeException
{
    /** @param array<string, mixed> $book */
    public function __construct(public readonly array $book)
    {
        parent::__construct('A Book with this ISBN already exists.');
    }
}
