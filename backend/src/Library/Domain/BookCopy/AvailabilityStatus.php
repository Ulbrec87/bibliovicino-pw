<?php

declare(strict_types=1);

namespace App\Library\Domain\BookCopy;

enum AvailabilityStatus: string
{
    case AVAILABLE = 'AVAILABLE';
    case UNAVAILABLE = 'UNAVAILABLE';
}
