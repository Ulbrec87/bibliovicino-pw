<?php

declare(strict_types=1);

namespace App\ReadModel\Discovery\Infrastructure\Http;

use App\ReadModel\Discovery\Application\SearchBookCopies\DiscoveryBookCopyResult;
use App\ReadModel\Discovery\Application\SearchBookCopies\DiscoverySearchResult;

final class DiscoverySearchResponse
{
    /** @return array<string, mixed> */
    public static function fromResult(DiscoverySearchResult $result): array
    {
        return [
            'results' => array_map(self::bookCopy(...), $result->results),
            'meta' => [
                'radiusKm' => $result->radiusKm,
                'sort' => $result->sort->value,
                'returnedCount' => $result->returnedCount(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function bookCopy(DiscoveryBookCopyResult $result): array
    {
        return [
            'bookCopyId' => $result->bookCopyId,
            'cover' => $result->cover,
            'book' => [
                'title' => $result->title,
                'authors' => $result->authors,
                'publicationYear' => $result->publicationYear,
                'categories' => $result->categories,
            ],
            'owner' => [
                'id' => $result->ownerId,
                'displayName' => $result->ownerDisplayName,
            ],
            'approximateDistance' => [
                'kind' => $result->approximateDistance->kind,
                'kilometres' => $result->approximateDistance->kilometres,
            ],
            'offerModes' => $result->offerModes,
            'approximatePoint' => [
                'latitude' => $result->approximatePoint->latitude,
                'longitude' => $result->approximatePoint->longitude,
            ],
        ];
    }

    private function __construct()
    {
    }
}
