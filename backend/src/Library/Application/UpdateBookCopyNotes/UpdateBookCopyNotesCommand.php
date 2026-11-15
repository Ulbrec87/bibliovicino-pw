<?php

declare(strict_types=1);

namespace App\Library\Application\UpdateBookCopyNotes;

final readonly class UpdateBookCopyNotesCommand
{
    public function __construct(public string $ownerId, public string $bookCopyId, public ?string $notes)
    {
    }
}
