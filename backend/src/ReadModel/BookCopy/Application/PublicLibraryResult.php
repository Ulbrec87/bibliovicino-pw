<?php

declare(strict_types=1);

namespace App\ReadModel\BookCopy\Application;

final readonly class PublicLibraryResult
{
    public int $sharedCount;

    /** @param array{id: string, displayName: string} $owner
     *  @param list<PublicLibraryCopyResult> $bookCopies
     */
    public function __construct(public array $owner, public array $bookCopies)
    {
        $this->sharedCount = count($bookCopies);
    }
}
