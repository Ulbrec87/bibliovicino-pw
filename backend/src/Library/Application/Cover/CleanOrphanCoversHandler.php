<?php

declare(strict_types=1);

namespace App\Library\Application\Cover;

use App\Shared\Application\Clock;

final readonly class CleanOrphanCoversHandler
{
    public function __construct(
        private CoverCopyAccess $copies,
        private CoverStorage $storage,
        private Clock $clock,
    ) {
    }

    public function handle(CleanOrphanCoversCommand $command): int
    {
        $count = 0;
        $threshold = $this->clock->now()->getTimestamp() - 86400;
        foreach ($this->storage->candidates() as $candidate) {
            if ($candidate['modifiedAt'] >= $threshold) {
                continue;
            }
            if ($this->copies->isReferenced($candidate['copyId'], $candidate['reference'])) {
                continue;
            }
            if ($command->delete) {
                $this->storage->delete($candidate['copyId'], $candidate['reference']);
            }
            ++$count;
        }
        return $count;
    }
}
