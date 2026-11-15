<?php

declare(strict_types=1);

namespace App\ReadModel\Discovery\Application\SearchBookCopies;

final readonly class DiscoverySearchResult
{
    /** @param list<DiscoveryBookCopyResult> $results */
    public function __construct(
        public array $results,
        public int $radiusKm,
        public DiscoverySort $sort,
    ) {
    }

    public function returnedCount(): int
    {
        return count($this->results);
    }
}
