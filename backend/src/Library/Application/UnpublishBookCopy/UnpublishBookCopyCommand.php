<?php

declare(strict_types=1);

namespace App\Library\Application\UnpublishBookCopy;

final readonly class UnpublishBookCopyCommand
{
    public function __construct(public string $ownerId, public string $bookCopyId)
    {
    }
}
