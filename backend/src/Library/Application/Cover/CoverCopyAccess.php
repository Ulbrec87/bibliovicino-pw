<?php

declare(strict_types=1);

namespace App\Library\Application\Cover;

use App\Library\Domain\BookCopy\BookCopy;

interface CoverCopyAccess
{
    public function save(BookCopy $copy): void;
    /** @return array{source: string, reference: ?string}|null */
    public function visible(string $copyId, string $viewerId): ?array;
    public function isReferenced(string $copyId, string $reference): bool;
}
