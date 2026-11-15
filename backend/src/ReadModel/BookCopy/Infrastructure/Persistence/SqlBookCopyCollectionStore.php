<?php

declare(strict_types=1);

namespace App\ReadModel\BookCopy\Infrastructure\Persistence;

use App\Library\Application\PublicApi\CoverView;
use App\ReadModel\BookCopy\Application\BookCopyCollectionStore;
use Doctrine\DBAL\Connection;

final readonly class SqlBookCopyCollectionStore implements BookCopyCollectionStore
{
    public function __construct(private Connection $connection, private CoverView $covers) {}

    public function ownedCopies(string $ownerId): array
    {
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT copy.id::text AS id, book.title, copy.is_published, copy.availability_status,
                   copy.cover_source, copy.cover_reference, copy.offered_for_loan,
                   (SELECT request.status FROM loan_request request
                    WHERE request.book_copy_id = copy.id AND request.status IN ('PENDING', 'ACCEPTED', 'LENT')
                    LIMIT 1) AS active_loan_status,
                   COALESCE((SELECT json_agg(author.name ORDER BY link.position)
                     FROM library_book_author link JOIN library_author author ON author.id = link.author_id
                     WHERE link.book_id = book.id), '[]'::json)::text AS authors_json
            FROM library_book_copy copy JOIN library_book book ON book.id = copy.book_id
            WHERE copy.owner_id = :ownerId AND copy.archived_at IS NULL
            ORDER BY copy.created_at DESC, copy.id DESC
            SQL, ['ownerId' => $ownerId]);

        return array_map(fn (array $row): array => [
            'id' => $row['id'],
            'cover' => $this->covers->present($row['id'], $row['cover_source'], $row['cover_reference']),
            'title' => $row['title'],
            'authors' => json_decode((string) $row['authors_json'], true, 512, JSON_THROW_ON_ERROR),
            'isPublished' => in_array($row['is_published'], [true, 't', '1', 1], true),
            'offerModes' => in_array($row['offered_for_loan'], [true, 't', '1', 1], true) ? ['loan'] : [],
            'availabilityStatus' => $row['availability_status'],
            'activeLoanStatus' => $row['active_loan_status'],
        ], $rows);
    }
}
