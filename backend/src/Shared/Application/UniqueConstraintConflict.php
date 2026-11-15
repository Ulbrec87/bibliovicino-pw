<?php

declare(strict_types=1);

namespace App\Shared\Application;

use RuntimeException;
use Throwable;

final class UniqueConstraintConflict extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('A unique constraint was violated.', previous: $previous);
    }
}
