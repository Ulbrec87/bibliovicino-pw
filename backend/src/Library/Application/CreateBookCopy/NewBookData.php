<?php

declare(strict_types=1);

namespace App\Library\Application\CreateBookCopy;

use App\Library\Domain\Book\Isbn;
use App\Library\Domain\Category\CategoryId;

final readonly class NewBookData
{
    /**
     * @param list<string> $authors
     * @param list<CategoryId> $categoryIds
     */
    public function __construct(
        public string $title,
        public array $authors,
        public array $categoryIds,
        public ?int $publicationYear,
        public ?Isbn $isbn,
    ) {
    }
}
