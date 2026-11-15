<?php

declare(strict_types=1);

namespace App\Library\Application\Cover;

use App\Library\Domain\BookCopy\BookCopyCover;

final readonly class ChangeCoverCommand
{
    public function __construct(
        public string $copyId,
        public string $ownerId,
        public ?BookCopyCover $cover = null,
        public ?string $temporaryPath = null,
    ) {
    }
}
