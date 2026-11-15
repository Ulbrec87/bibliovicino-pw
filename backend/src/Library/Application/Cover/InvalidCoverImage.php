<?php

declare(strict_types=1);

namespace App\Library\Application\Cover;

use RuntimeException;

final class InvalidCoverImage extends RuntimeException
{
    public function __construct(public readonly bool $tooLarge = false)
    {
        parent::__construct('Invalid cover image.');
    }
}
