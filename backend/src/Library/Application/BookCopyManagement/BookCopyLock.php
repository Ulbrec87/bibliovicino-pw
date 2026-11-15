<?php

declare(strict_types=1);

namespace App\Library\Application\BookCopyManagement;

use App\Library\Domain\BookCopy\BookCopy;

interface BookCopyLock
{
    // Lock sulla copia del libro selezionato.
    public function lock(string $id): ?BookCopy;
}
