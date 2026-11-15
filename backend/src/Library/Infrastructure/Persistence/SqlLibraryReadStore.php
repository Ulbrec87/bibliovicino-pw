<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Persistence;

use App\Library\Application\LibraryReadStore;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final readonly class SqlLibraryReadStore implements LibraryReadStore
{
    public function __construct(private Connection $connection)
    {
    }

    public function searchBooks(string $query, ?string $canonicalIsbn, int $page): array
    {
        $params = ['limit' => 21, 'offset' => ($page - 1) * 20];
        $types = ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER];
        if (null !== $canonicalIsbn) {
            $predicate = 'book.isbn = :isbn';
            $params['isbn'] = $canonicalIsbn;
        } else {
            $predicate = "(book.title ILIKE :pattern ESCAPE '!' OR EXISTS (SELECT 1 FROM library_book_author matched_link JOIN library_author matched_author ON matched_author.id = matched_link.author_id WHERE matched_link.book_id = book.id AND matched_author.name ILIKE :pattern ESCAPE '!'))";
            $params['pattern'] = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query).'%';
        }
        $rank = null === $canonicalIsbn ? "CASE WHEN book.title ILIKE :pattern ESCAPE '!' THEN 0 ELSE 1 END ASC," : '';
        $sql = <<<SQL
            SELECT book.id::text AS id, book.title, book.publication_year, book.isbn,
                   COALESCE((SELECT json_agg(author.name ORDER BY link.position)
                     FROM library_book_author link JOIN library_author author ON author.id = link.author_id
                     WHERE link.book_id = book.id), '[]'::json)::text AS authors_json
            FROM library_book book WHERE {$predicate}
            ORDER BY {$rank} book.title ASC, book.id ASC LIMIT :limit OFFSET :offset
            SQL;
        $rows = $this->connection->fetchAllAssociative($sql, $params, $types);
        $hasMore = count($rows) > 20;

        return ['results' => array_map(self::bookRow(...), array_slice($rows, 0, 20)), 'hasMore' => $hasMore];
    }

    public function bookByIsbn(string $canonicalIsbn): ?array
    {
        $row = $this->connection->fetchAssociative(<<<'SQL'
            SELECT book.id::text AS id, book.title, book.publication_year, book.isbn,
                   COALESCE((SELECT json_agg(author.name ORDER BY link.position)
                     FROM library_book_author link JOIN library_author author ON author.id = link.author_id
                     WHERE link.book_id = book.id), '[]'::json)::text AS authors_json
            FROM library_book book WHERE book.isbn = :isbn
            SQL, ['isbn' => $canonicalIsbn]);

        return false === $row ? null : self::bookRow($row);
    }

    public function activeCategories(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT id::text, name, slug FROM library_category WHERE active = TRUE ORDER BY name ASC, id ASC');
        return array_map(static fn (array $row): array => ['id' => $row['id'], 'name' => $row['name'], 'slug' => $row['slug']], $rows);
    }

    private static function bookRow(array $row): array
    {
        return [
            'id' => $row['id'],
            'title' => $row['title'],
            'authors' => json_decode((string) $row['authors_json'], true, 512, JSON_THROW_ON_ERROR),
            'publicationYear' => null === $row['publication_year'] ? null : (int) $row['publication_year'],
            'isbn' => $row['isbn'],
        ];
    }
}
