<?php

declare(strict_types=1);

namespace App\ReadModel\Discovery\Application\SearchBookCopies;

final readonly class DiscoverySearchCriteria
{
    public function __construct(
        public string $currentUserId,
        public ?string $query,
        public int $radiusMetres,
        public DiscoverySort $sort,
    ) {
    }
}
