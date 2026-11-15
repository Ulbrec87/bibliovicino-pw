<?php

declare(strict_types=1);

namespace App\Library\Application\CreateBookCopy;

final readonly class CreateBookCopyResult
{
    public function __construct(
        public string $id,
        public string $bookId,
        public bool $isPublished,
    ) {
    }
}
