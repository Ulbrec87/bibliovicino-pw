<?php

declare(strict_types=1);

namespace App\ReadModel\Discovery\Infrastructure\Persistence;

use App\Library\Application\PublicApi\CoverView;
use App\ReadModel\Discovery\Application\SearchBookCopies\DiscoveryBookCopyRecord;
use App\ReadModel\Discovery\Application\SearchBookCopies\DiscoveryBookCopySearch;
use App\ReadModel\Discovery\Application\SearchBookCopies\DiscoverySearchCriteria;
use App\ReadModel\Discovery\Application\SearchBookCopies\DiscoverySort;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use LogicException;

final readonly class PostgisDiscoveryBookCopySearch implements DiscoveryBookCopySearch
{
    private const int RESULT_LIMIT = 50;

    public function __construct(private Connection $connection, private CoverView $covers)
    {
    }

    public function search(DiscoverySearchCriteria $criteria): ?array
    {
        $hasOrigin = $this->connection->fetchOne(
            'SELECT 1 FROM identity_user WHERE id = :currentUserId AND discovery_location IS NOT NULL',
            ['currentUserId' => $criteria->currentUserId],
            ['currentUserId' => ParameterType::STRING],
        );

        if (false === $hasOrigin) {
            return null;
        }

        $params = [
            'currentUserId' => $criteria->currentUserId,
            'radiusMetres' => $criteria->radiusMetres,
        ];
        $types = [
            'currentUserId' => ParameterType::STRING,
            'radiusMetres' => ParameterType::INTEGER,
        ];
        $textPredicate = '';
        $titleMatchOrder = '';

        if (null !== $criteria->query) {
            $params['containsPattern'] = self::containsPattern($criteria->query);
            $types['containsPattern'] = ParameterType::STRING;
            $titleMatch = "book.title ILIKE :containsPattern ESCAPE '!'";
            $textPredicate = <<<SQL
                  AND (
                      {$titleMatch}
                      OR EXISTS (
                          SELECT 1
                          FROM library_book_author matched_authorship
                          JOIN library_author matched_author
                            ON matched_author.id = matched_authorship.author_id
                          WHERE matched_authorship.book_id = book.id
                            AND matched_author.name ILIKE :containsPattern ESCAPE '!'
                      )
                  )
                SQL;
            $titleMatchOrder = "CASE WHEN {$titleMatch} THEN 0 ELSE 1 END ASC, ";
        }

        $orderBy = match ($criteria->sort) {
            DiscoverySort::RELEVANCE => $titleMatchOrder.'distance_metres ASC, book.title ASC, copy.id ASC',
            DiscoverySort::DISTANCE => 'distance_metres ASC, '.$titleMatchOrder.'book.title ASC, copy.id ASC',
        };

        $sql = <<<SQL
            SELECT
                copy.id::text AS book_copy_id,
                copy.offered_for_loan, copy.cover_source, copy.cover_reference,
                book.title,
                book.publication_year,
                owner.id::text AS owner_id,
                owner.display_name AS owner_display_name,
                ST_Distance(owner.discovery_location, searcher.discovery_location) AS distance_metres,
                ST_Y(owner.discovery_location::geometry) AS approximate_latitude,
                ST_X(owner.discovery_location::geometry) AS approximate_longitude,
                COALESCE((
                    SELECT json_agg(author.name ORDER BY authorship.position)
                    FROM library_book_author authorship
                    JOIN library_author author ON author.id = authorship.author_id
                    WHERE authorship.book_id = book.id
                ), '[]'::json)::text AS authors_json,
                COALESCE((
                    SELECT json_agg(category.name ORDER BY category.name)
                    FROM library_book_category classification
                    JOIN library_category category ON category.id = classification.category_id
                    WHERE classification.book_id = book.id
                ), '[]'::json)::text AS categories_json
            FROM library_book_copy copy
            JOIN library_book book ON book.id = copy.book_id
            JOIN identity_user owner ON owner.id = copy.owner_id
            JOIN identity_user searcher ON searcher.id = :currentUserId
            WHERE searcher.discovery_location IS NOT NULL
              AND owner.discovery_location IS NOT NULL
              AND copy.is_published = TRUE
              AND copy.archived_at IS NULL
              AND copy.availability_status = 'AVAILABLE'
              AND copy.offered_for_loan = TRUE
              AND NOT EXISTS (SELECT 1 FROM loan_request request WHERE request.book_copy_id = copy.id AND request.status IN ('PENDING', 'ACCEPTED', 'LENT'))
              AND copy.owner_id <> searcher.id
              AND ST_DWithin(
                  owner.discovery_location,
                  searcher.discovery_location,
                  CAST(:radiusMetres AS DOUBLE PRECISION)
              )
            {$textPredicate}
            ORDER BY {$orderBy}
            LIMIT %d
            SQL;

        $rows = $this->connection->fetchAllAssociative(
            sprintf($sql, self::RESULT_LIMIT),
            $params,
            $types,
        );

        return array_map($this->toRecord(...), $rows);
    }

    private static function containsPattern(string $query): string
    {
        return '%'.str_replace(
            ['!', '%', '_'],
            ['!!', '!%', '!_'],
            $query,
        ).'%';
    }

    /** @param array<string, mixed> $row */
    private function toRecord(array $row): DiscoveryBookCopyRecord
    {
        foreach (['distance_metres', 'approximate_latitude', 'approximate_longitude'] as $numericField) {
            if (!is_numeric($row[$numericField] ?? null)) {
                throw new LogicException('The Discovery projection returned invalid spatial data.');
            }
        }

        return new DiscoveryBookCopyRecord(
            (string) $row['book_copy_id'],
            (string) $row['title'],
            json_decode($row['authors_json'], true, 512, JSON_THROW_ON_ERROR),
            null === $row['publication_year'] ? null : (int) $row['publication_year'],
            json_decode($row['categories_json'], true, 512, JSON_THROW_ON_ERROR),
            (string) $row['owner_id'],
            (string) $row['owner_display_name'],
            (float) $row['distance_metres'],
            (float) $row['approximate_latitude'],
            (float) $row['approximate_longitude'],
            in_array($row['offered_for_loan'], [true, 't', '1', 1], true),
            $this->covers->present($row['book_copy_id'], $row['cover_source'], $row['cover_reference']),
        );
    }
}
