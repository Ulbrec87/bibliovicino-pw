<?php

declare(strict_types=1);

namespace App\Library\Application\Cover;

final readonly class ReadCoverCommand
{
    public function __construct(
        public string $copyId,
        public string $viewerId,
        public string $variant,
    ) {
    }
}
