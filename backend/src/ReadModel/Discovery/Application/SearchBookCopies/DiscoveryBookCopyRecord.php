<?php

declare(strict_types=1);

namespace App\ReadModel\Discovery\Application\SearchBookCopies;

final readonly class DiscoveryBookCopyRecord
{
    /**
     * @param list<string> $authors
     * @param list<string> $categories
     * @param array{source: string, previewUrl: string, largeUrl: string, reference?: string}|null $cover
     */
    public function __construct(
        public string $bookCopyId,
        public string $title,
        public array $authors,
        public ?int $publicationYear,
        public array $categories,
        public string $ownerId,
        public string $ownerDisplayName,
        public float $distanceMetres,
        public float $approximateLatitude,
        public float $approximateLongitude,
        public bool $offeredForLoan = true,
        public ?array $cover = null,
    ) {
    }
}
