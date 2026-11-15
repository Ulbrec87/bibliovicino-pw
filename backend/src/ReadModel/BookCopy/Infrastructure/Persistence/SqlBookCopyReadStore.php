<?php

declare(strict_types=1);

namespace App\ReadModel\BookCopy\Infrastructure\Persistence;

use App\Library\Application\PublicApi\CoverView;
use App\ReadModel\BookCopy\Application\BookCopyDetailResult;
use App\ReadModel\BookCopy\Application\PublicLibraryCopyResult;
use App\ReadModel\BookCopy\Application\PublicLibraryResult;
use App\ReadModel\BookCopy\Application\BookCopyReadStore;
use App\ReadModel\Discovery\Application\SearchBookCopies\ApproximateDistanceResult;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class SqlBookCopyReadStore implements BookCopyReadStore
{
    public function __construct(private Connection $connection, private CoverView $covers)
    {
    }

    public function detail(string $id, string $viewerId): ?BookCopyDetailResult
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        $row = $this->connection->fetchAssociative(<<<'SQL'
            SELECT copy.id::text, copy.notes, copy.is_published, copy.availability_status, copy.cover_source, copy.cover_reference,
                   copy.offered_for_loan,
                   (SELECT request.status FROM loan_request request WHERE request.book_copy_id = copy.id AND request.status IN ('PENDING', 'ACCEPTED', 'LENT') LIMIT 1) AS active_loan_status,
                   owner.id::text AS owner_id, owner.display_name,
                   book.id::text AS book_id, book.title, book.publication_year, book.isbn,
                   CASE WHEN copy.owner_id <> viewer.id THEN
                       ST_Distance(owner.discovery_location, viewer.discovery_location)
                   END AS distance_metres
            FROM library_book_copy copy
            JOIN library_book book ON book.id = copy.book_id
            JOIN identity_user owner ON owner.id = copy.owner_id
            JOIN identity_user viewer ON viewer.id = :viewerId
            WHERE copy.id = :id AND copy.archived_at IS NULL
              AND (copy.owner_id = viewer.id OR copy.is_published = TRUE)
            SQL, ['id' => $id, 'viewerId' => $viewerId]);
        if (false === $row) {
            return null;
        }
        $authors = $this->authorsByBookIds([$row['book_id']]);
        $categories = $this->connection->fetchFirstColumn(<<<'SQL'
            SELECT category.name
            FROM library_book_category link
            JOIN library_category category ON category.id = link.category_id
            WHERE link.book_id = :bookId
            ORDER BY category.name, category.id
            SQL, ['bookId' => $row['book_id']]);
        return new BookCopyDetailResult(
            $row['id'],
            [
                'id' => $row['book_id'], 'title' => $row['title'],
                'authors' => $authors[$row['book_id']] ?? [],
                'categories' => $categories,
                'publicationYear' => null === $row['publication_year'] ? null : (int) $row['publication_year'],
                'isbn' => $row['isbn'],
            ],
            $row['notes'],
            $row['owner_id'] === $viewerId,
            in_array($row['is_published'], [true, 't', '1', 1], true),
            $row['availability_status'],
            ['id' => $row['owner_id'], 'displayName' => $row['display_name']],
            null === $row['distance_metres'] ? null : ApproximateDistanceResult::fromMetres((float) $row['distance_metres']),
            $this->covers->present($row['id'], $row['cover_source'], $row['cover_reference'], $row['owner_id'] === $viewerId),
            $row['active_loan_status'],
            $row['owner_id'] !== $viewerId && null === $row['active_loan_status']
                && in_array($row['is_published'], [true, 't', '1', 1], true)
                && in_array($row['offered_for_loan'], [true, 't', '1', 1], true)
                && $row['availability_status'] === 'AVAILABLE',
        );
    }

    public function publicLibrary(string $ownerId): ?PublicLibraryResult
    {
        if (!Uuid::isValid($ownerId)) {
            return null;
        }

        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT owner.id::text AS owner_id, owner.display_name,
                   copy.id::text AS copy_id, copy.availability_status, copy.cover_source, copy.cover_reference,
                   (SELECT request.status FROM loan_request request WHERE request.book_copy_id = copy.id AND request.status IN ('PENDING', 'ACCEPTED', 'LENT') LIMIT 1) AS active_loan_status,
                   book.id::text AS book_id, book.title
            FROM identity_user owner
            LEFT JOIN library_book_copy copy ON copy.owner_id = owner.id
                AND copy.is_published = TRUE AND copy.archived_at IS NULL
            LEFT JOIN library_book book ON book.id = copy.book_id
            WHERE owner.id = :ownerId
            ORDER BY copy.created_at DESC, copy.id DESC
            SQL, ['ownerId' => $ownerId]);
        if ([] === $rows) {
            return null;
        }
        $owner = ['id' => $rows[0]['owner_id'], 'displayName' => $rows[0]['display_name']];
        if (null === $rows[0]['copy_id']) {
            return new PublicLibraryResult($owner, []);
        }
        $authors = $this->authorsByBookIds(array_column($rows, 'book_id'));
        $copies = [];
        foreach ($rows as $row) {
            $copies[] = new PublicLibraryCopyResult(
                $row['copy_id'], $row['title'],
                $authors[$row['book_id']] ?? [],
                $row['availability_status'],
                $this->covers->present($row['copy_id'], $row['cover_source'], $row['cover_reference']),
                $row['active_loan_status'],
            );
        }
        return new PublicLibraryResult($owner, $copies);
    }

    /**
     * @param list<string> $bookIds
     * @return array<string, list<string>>
     */
    private function authorsByBookIds(array $bookIds): array
    {
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT link.book_id::text AS book_id, author.name
            FROM library_book_author link
            JOIN library_author author ON author.id = link.author_id
            WHERE link.book_id IN (:bookIds)
            ORDER BY link.book_id, link.position
            SQL, ['bookIds' => array_values(array_unique($bookIds))], ['bookIds' => ArrayParameterType::STRING]);
        $authors = [];
        foreach ($rows as $row) {
            $authors[$row['book_id']][] = $row['name'];
        }
        return $authors;
    }
}
