<?php

declare(strict_types=1);

namespace App\ReadModel\BookCopy\Application;

final readonly class PublicLibraryCopyResult
{
    /**
     * @param list<string> $authors
     * @param array{source: string, previewUrl: string, largeUrl: string, reference?: string}|null $cover
     */
    public function __construct(
        public string $id,
        public string $title,
        public array $authors,
        public string $availabilityStatus,
        public ?array $cover = null,
        public ?string $activeLoanStatus = null,
    ) {
    }
}
