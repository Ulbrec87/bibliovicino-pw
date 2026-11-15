<?php

declare(strict_types=1);

namespace App\Library\Application\BookCopyManagement;

use App\Library\Domain\BookCopy\BookCopy;
use App\Library\Domain\BookCopy\BookCopyId;
use App\Library\Domain\BookCopy\BookCopyRepository;
use InvalidArgumentException;

final readonly class OwnedBookCopyService
{
    public function __construct(private BookCopyRepository $copies)
    {
    }

    public function get(string $id, string $ownerId): BookCopy
    {
        try {
            $copyId = BookCopyId::fromString($id);
        } catch (InvalidArgumentException) {
            throw new BookCopyNotFound();
        }
        $copy = $this->copies->findById($copyId);
        if (null === $copy || $copy->ownerId()->toString() !== $ownerId) {
            throw new BookCopyNotFound();
        }
        return $copy;
    }
}
