<?php

declare(strict_types=1);

namespace App\ReadModel\Discovery\Application\SearchBookCopies;

final readonly class SearchBookCopiesCommand
{
    public function __construct(
        public string $currentUserId,
        public ?string $query = null,
        public ?int $radiusKm = null,
        public ?string $sort = null,
    ) {
    }
}
