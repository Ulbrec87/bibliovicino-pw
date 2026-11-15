<?php

declare(strict_types=1);

namespace App\Library\Application\Cover;

use App\Library\Domain\BookCopy\BookCopyCover;

interface ExternalCoverReferences
{
    public function issue(int $coverId): string;
    public function resolve(string $reference): BookCopyCover;
}
