<?php

declare(strict_types=1);

namespace App\ReadModel\Discovery\Application\SearchBookCopies;

final readonly class SearchBookCopiesHandler
{
    private const int DEFAULT_RADIUS_KM = 5;
    private const int MAX_QUERY_LENGTH = 255;
    private const array SUPPORTED_RADII_KM = [1, 2, 5, 10];

    public function __construct(private DiscoveryBookCopySearch $bookCopies)
    {
    }

    public function handle(SearchBookCopiesCommand $command): DiscoverySearchResult
    {
        $query = self::normaliseQuery($command->query);
        $radiusKm = $command->radiusKm ?? self::DEFAULT_RADIUS_KM;
        $errors = [];

        if (null !== $query && mb_strlen($query) > self::MAX_QUERY_LENGTH) {
            $errors['q'][] = 'This value is too long. It should have 255 characters or less.';
        }

        if (!in_array($radiusKm, self::SUPPORTED_RADII_KM, true)) {
            $errors['radiusKm'][] = 'Choose one of 1, 2, 5 or 10.';
        }

        if (null !== $command->sort && !in_array($command->sort, array_column(DiscoverySort::cases(), 'value'), true)) {
            $errors['sort'][] = 'Choose relevance or distance.';
        }

        if ([] !== $errors) {
            throw new InvalidDiscoverySearch($errors);
        }

        $sort = null === $query
            ? DiscoverySort::DISTANCE
            : DiscoverySort::from($command->sort ?? DiscoverySort::RELEVANCE->value);

        $records = $this->bookCopies->search(new DiscoverySearchCriteria(
            $command->currentUserId,
            $query,
            $radiusKm * 1000,
            $sort,
        ));

        if (null === $records) {
            throw new DiscoveryLocationRequired();
        }

        return new DiscoverySearchResult(
            array_map(self::toResult(...), $records),
            $radiusKm,
            $sort,
        );
    }

    private static function normaliseQuery(?string $query): ?string
    {
        $query = trim($query ?? '');

        return '' === $query ? null : $query;
    }

    private static function toResult(DiscoveryBookCopyRecord $record): DiscoveryBookCopyResult
    {
        return new DiscoveryBookCopyResult(
            $record->bookCopyId,
            $record->title,
            $record->authors,
            $record->publicationYear,
            $record->categories,
            $record->ownerId,
            $record->ownerDisplayName,
            ApproximateDistanceResult::fromMetres($record->distanceMetres),
            array_values(array_filter([
                $record->offeredForLoan ? 'loan' : null,
            ])),
            new ApproximatePointResult(
                $record->approximateLatitude,
                $record->approximateLongitude,
            ),
            $record->cover,
        );
    }
}
