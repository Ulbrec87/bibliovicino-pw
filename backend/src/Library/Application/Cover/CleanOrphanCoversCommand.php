<?php

declare(strict_types=1);

namespace App\Library\Application\Cover;

final readonly class CleanOrphanCoversCommand
{
    public function __construct(public bool $delete = false)
    {
    }
}
