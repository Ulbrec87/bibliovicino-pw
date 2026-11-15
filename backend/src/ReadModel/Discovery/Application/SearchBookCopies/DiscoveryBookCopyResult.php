<?php

declare(strict_types=1);

namespace App\ReadModel\Discovery\Application\SearchBookCopies;

final readonly class DiscoveryBookCopyResult
{
    /**
     * @param list<string> $authors
     * @param list<string> $categories
     * @param list<string> $offerModes
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
        public ApproximateDistanceResult $approximateDistance,
        public array $offerModes,
        public ApproximatePointResult $approximatePoint,
        public ?array $cover = null,
    ) {
    }
}
