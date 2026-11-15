<?php

declare(strict_types=1);

namespace App\Library\Application\UpdateBookCopyNotes;

use App\Library\Application\BookCopyManagement\OwnedBookCopyService;
use App\Library\Domain\BookCopy\BookCopyRepository;
use App\Shared\Application\Clock;

final readonly class UpdateBookCopyNotesHandler
{
    public function __construct(
        private OwnedBookCopyService $ownedCopy,
        private BookCopyRepository $copies,
        private Clock $clock,
    ) {
    }

    public function handle(UpdateBookCopyNotesCommand $command): void
    {
        $copy = $this->ownedCopy->get($command->bookCopyId, $command->ownerId);
        $copy->changeDetails($command->notes, $this->clock->now());
        $this->copies->save($copy);
    }
}
