<?php

declare(strict_types=1);

namespace App\ReadModel\BookCopy\Application;

interface BookCopyCollectionStore
{
    /**
     * @return list<array{
     *     id: string,
     *     cover: array{source: string, previewUrl: string, largeUrl: string, reference?: string}|null,
     *     title: string,
     *     authors: list<string>,
     *     isPublished: bool,
     *     offerModes: list<'loan'>,
     *     availabilityStatus: string,
     *     activeLoanStatus: string|null
     * }>
     */
    public function ownedCopies(string $ownerId): array;
}
