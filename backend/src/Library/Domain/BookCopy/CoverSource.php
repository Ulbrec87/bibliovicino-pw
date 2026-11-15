<?php

declare(strict_types=1);

namespace App\Library\Domain\BookCopy;

enum CoverSource: string
{
    case NONE = 'none';
    case EXTERNAL = 'external';
    case UPLOADED = 'uploaded';
}
