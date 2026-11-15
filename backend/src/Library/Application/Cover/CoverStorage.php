<?php

declare(strict_types=1);

namespace App\Library\Application\Cover;

interface CoverStorage
{
    public function write(string $copyId, string $reference, ProcessedCover $images): void;
    /** @return resource|null */
    public function open(string $copyId, string $reference, string $variant): mixed;
    public function delete(string $copyId, string $reference): void;
    /** @return iterable<array{copyId: string, reference: string, modifiedAt: int}> */
    public function candidates(): iterable;
}
