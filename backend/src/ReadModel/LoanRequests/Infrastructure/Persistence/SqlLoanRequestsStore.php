<?php

declare(strict_types=1);

namespace App\ReadModel\LoanRequests\Infrastructure\Persistence;

use App\Library\Application\PublicApi\CoverView;
use App\ReadModel\LoanRequests\Application\LoanRequestsResult;
use App\ReadModel\LoanRequests\Application\LoanRequestsStore;
use Doctrine\DBAL\Connection;

final readonly class SqlLoanRequestsStore implements LoanRequestsStore
{
    public function __construct(private Connection $connection, private CoverView $covers) {}

    public function forParticipant(string $viewerId): LoanRequestsResult
    {
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT request.id::text AS id, request.status, request.requested_at, request.updated_at,
                   copy.id::text AS copy_id,
                   copy.owner_id::text AS owner_id, copy.is_published, copy.archived_at,
                   copy.cover_source, copy.cover_reference, book.title,
                   owner.display_name AS owner_name, requester.display_name AS requester_name,
                   CASE WHEN request.requester_id = :viewerId AND request.status IN ('ACCEPTED', 'LENT')
                        THEN owner.email ELSE NULL END AS owner_email,
                   COALESCE((SELECT json_agg(author.name ORDER BY link.position)
                     FROM library_book_author link JOIN library_author author ON author.id = link.author_id
                     WHERE link.book_id = book.id), '[]'::json)::text AS authors_json
            FROM loan_request request
            JOIN library_book_copy copy ON copy.id = request.book_copy_id
            JOIN library_book book ON book.id = copy.book_id
            JOIN identity_user owner ON owner.id = copy.owner_id
            JOIN identity_user requester ON requester.id = request.requester_id
            WHERE request.requester_id = :viewerId OR copy.owner_id = :viewerId
            ORDER BY CASE WHEN copy.owner_id = :viewerId AND request.status = 'PENDING' THEN 0
                           WHEN request.status IN ('PENDING', 'ACCEPTED', 'LENT') THEN 1 ELSE 2 END,
                     request.updated_at DESC, request.id DESC
            SQL, ['viewerId' => $viewerId]);

        $result = ['received' => [], 'sent' => []];
        foreach ($rows as $row) {
            $owner = $row['owner_id'] === $viewerId;
            $coverVisible = null === $row['archived_at']
                && ($owner || in_array($row['is_published'], [true, 't', 1, '1'], true));
            $status = (string) $row['status'];
            $actions = $owner ? match ($status) {
                'PENDING' => ['accept', 'reject'],
                'ACCEPTED' => ['cancel', 'lend'],
                'LENT' => ['return'],
                default => [],
            } : match ($status) {
                'PENDING', 'ACCEPTED' => ['cancel'],
                default => [],
            };
            $item = [
                'id' => $row['id'],
                'status' => $status,
                'requestedAt' => $row['requested_at'],
                'updatedAt' => $row['updated_at'],
                'copy' => [
                    'id' => $row['copy_id'],
                    'title' => $row['title'],
                    'authors' => json_decode((string) $row['authors_json'], true, 512, JSON_THROW_ON_ERROR),
                    'cover' => $coverVisible ? $this->covers->present($row['copy_id'], $row['cover_source'], $row['cover_reference']) : null,
                    'canOpenDetail' => $coverVisible,
                ],
                'otherParticipant' => [
                    'displayName' => $owner ? $row['requester_name'] : $row['owner_name'],
                ],
                'actions' => $actions,
            ];
            if (!$owner && null !== $row['owner_email']) $item['ownerEmail'] = $row['owner_email'];
            $result[$owner ? 'received' : 'sent'][] = $item;
        }
        return new LoanRequestsResult($result['received'], $result['sent']);
    }
}
