<?php

declare(strict_types=1);

namespace App\Library\Application\PublishBookCopy;

final readonly class PublishBookCopyCommand
{
    public function __construct(public string $ownerId, public string $bookCopyId)
    {
    }
}
