<?php

declare(strict_types=1);

namespace App\Library\Application\ArchiveBookCopy;

final readonly class ArchiveBookCopyCommand
{
    public function __construct(public string $ownerId, public string $bookCopyId)
    {
    }
}
