<?php

declare(strict_types=1);

namespace App\Library\Application\Cover;

final readonly class ProcessedCover
{
    public function __construct(
        public string $thumbnail,
        public string $large,
    ) {
    }
}
