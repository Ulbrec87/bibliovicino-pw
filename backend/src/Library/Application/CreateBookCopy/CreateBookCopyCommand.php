<?php

declare(strict_types=1);

namespace App\Library\Application\CreateBookCopy;

use App\Library\Domain\Book\BookId;

final readonly class CreateBookCopyCommand
{
    public function __construct(
        public string $ownerId,
        public ?BookId $bookId,
        public ?NewBookData $newBook,
        public ?string $notes,
        public bool $publish,
        public ?\App\Library\Domain\BookCopy\BookCopyCover $cover = null,
    ) {
        if ((null === $bookId) === (null === $newBook)) {
            throw new InvalidBookCopyInput(['book' => ['Seleziona un libro esistente oppure aggiungine uno nuovo.']]);
        }
    }
}
