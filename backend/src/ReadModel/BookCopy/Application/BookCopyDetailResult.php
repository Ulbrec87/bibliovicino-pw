<?php

declare(strict_types=1);

namespace App\ReadModel\BookCopy\Application;

use App\ReadModel\Discovery\Application\SearchBookCopies\ApproximateDistanceResult;

final readonly class BookCopyDetailResult
{
    /**
     * @param array{id: string, title: string, authors: list<string>, categories: list<string>, publicationYear: ?int, isbn: ?string} $book
     * @param array{id: string, displayName: string} $owner
     * @param array{source: string, previewUrl: string, largeUrl: string, reference?: string}|null $cover
     */
    public function __construct(
        public string $id,
        public array $book,
        public ?string $notes,
        public bool $isOwner,
        public bool $isPublished,
        public string $availabilityStatus,
        public array $owner,
        public ?ApproximateDistanceResult $approximateDistance,
        public ?array $cover = null,
        public ?string $activeLoanStatus = null,
        public bool $canRequestLoan = false,
    ) {
    }
}
