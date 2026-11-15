<?php

declare(strict_types=1);

namespace App\Library\Application\Cover;

use App\Library\Application\BookCopyManagement\BookCopyNotFound;
use App\Library\Domain\BookCopy\BookCopyId;

final readonly class ReadCoverHandler
{
    public function __construct(
        private CoverCopyAccess $copies,
        private CoverStorage $storage,
    ) {
    }

    /** @return resource */
    public function handle(ReadCoverCommand $command): mixed
    {
        $cover = $this->copies->visible($command->copyId, $command->viewerId);
        if (null === $cover || 'uploaded' !== $cover['source']) {
            throw new BookCopyNotFound();
        }
        $copyId = BookCopyId::fromString($command->copyId)->toString();
        $stream = $this->storage->open($copyId, $cover['reference'], $command->variant);
        if (null === $stream) {
            throw new BookCopyNotFound();
        }
        return $stream;
    }
}
