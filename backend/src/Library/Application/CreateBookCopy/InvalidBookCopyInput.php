<?php

declare(strict_types=1);

namespace App\Library\Application\CreateBookCopy;

use RuntimeException;

final class InvalidBookCopyInput extends RuntimeException
{
    /** @param array<string, list<string>> $fields */
    public function __construct(public readonly array $fields)
    {
        parent::__construct('Invalid book-copy input.');
    }
}
