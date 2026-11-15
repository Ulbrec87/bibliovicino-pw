<?php

declare(strict_types=1);

namespace App\Tests\ReadModel\Discovery\Infrastructure\Persistence;

use App\ReadModel\Discovery\Application\SearchBookCopies\DiscoveryBookCopySearch;
use App\ReadModel\Discovery\Application\SearchBookCopies\DiscoverySearchCriteria;
use App\ReadModel\Discovery\Application\SearchBookCopies\DiscoverySort;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PostgisDiscoveryBookCopySearchTest extends KernelTestCase
{
    private Connection $connection;
    private DiscoveryBookCopySearch $search;
    private int $sequence = 0;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->connection = self::getContainer()->get(Connection::class);
        $this->search = self::getContainer()->get(DiscoveryBookCopySearch::class);
        $this->connection->executeStatement(
            'TRUNCATE TABLE library_book_copy, library_book_category, library_book_author, library_book, library_category, library_author, identity_user CASCADE',
        );
    }

    public function testTextSearchHandlesCaseAndLiteralWildcards(): void
    {
        $currentUserId = $this->insertUser('Current', 41.9028, 12.4964);
        $ownerId = $this->insertUser('Owner', 41.9028, 12.5064);
        $duneCopyId = $this->insertDiscoverableCopy(
            $ownerId,
            'Dune Messiah',
            ['Frank Herbert', 'Brian Herbert'],
            ['Fantascienza', 'Classici'],
            1969,
        );
        $authorCopyId = $this->insertDiscoverableCopy(
            $ownerId,
            'The Left Hand of Darkness',
            ['Ursula Le Guin'],
            ['Narrativa'],
        );
        $percentCopyId = $this->insertDiscoverableCopy($ownerId, '100% reale', ['Percent Author'], ['Speciale']);
        $this->insertDiscoverableCopy($ownerId, '100X reale', ['Plain Author'], ['Generale']);
        $underscoreCopyId = $this->insertDiscoverableCopy($ownerId, 'Under_score', ['Symbol Author'], ['Simboli']);
        $this->insertDiscoverableCopy($ownerId, 'UnderXscore', ['Other Author'], ['Altro']);

        $titleResults = $this->results($currentUserId, 'dUnE');
        self::assertCount(1, $titleResults);
        self::assertSame($duneCopyId, $titleResults[0]->bookCopyId);
        self::assertSame(['Frank Herbert', 'Brian Herbert'], $titleResults[0]->authors);
        self::assertSame(['Classici', 'Fantascienza'], $titleResults[0]->categories);
        self::assertSame(1969, $titleResults[0]->publicationYear);

        $authorResults = $this->results($currentUserId, 'uRsUlA');
        self::assertSame([$authorCopyId], array_column($authorResults, 'bookCopyId'));

        $percentResults = $this->results($currentUserId, '%');
        self::assertSame([$percentCopyId], array_column($percentResults, 'bookCopyId'));

        $underscoreResults = $this->results($currentUserId, '_');
        self::assertSame([$underscoreCopyId], array_column($underscoreResults, 'bookCopyId'));
    }

    public function testSearchFiltersByRadiusAndVisibility(): void
    {
        $currentUserId = $this->insertUser('Current', 41.9028, 12.4964);
        $nearOwnerId = $this->insertUser('Near owner', 41.9028, 12.5064);
        $farOwnerId = $this->insertUser('Far owner', 41.9028, 12.6964);
        $ownerWithoutLocationId = $this->insertUser('Hidden owner');
        $expectedCopyId = $this->insertDiscoverableCopy($nearOwnerId, 'Visible', ['Visible Author'], ['Visible Category']);

        $this->insertDiscoverableCopy($currentUserId, 'Own', ['Own Author'], ['Own Category']);
        $this->insertDiscoverableCopy($farOwnerId, 'Far', ['Far Author'], ['Far Category']);
        $this->insertDiscoverableCopy($ownerWithoutLocationId, 'No location', ['Hidden Author'], ['Hidden Category']);
        $this->insertDiscoverableCopy($nearOwnerId, 'Private', ['Private Author'], ['Private Category'], isPublished: false);
        $this->insertDiscoverableCopy($nearOwnerId, 'Unavailable', ['Unavailable Author'], ['Unavailable Category'], availability: 'UNAVAILABLE');
        $this->insertDiscoverableCopy($nearOwnerId, 'Not offered', ['Reader'], ['Other'], isPublished: false, offeredForLoan: false);
        $this->insertDiscoverableCopy(
            $nearOwnerId,
            'Archived',
            ['Archived Author'],
            ['Archived Category'],
            isPublished: false,
            archivedAt: '2026-09-01 09:00:00',
        );

        $results = $this->results($currentUserId, null, 5, DiscoverySort::DISTANCE);

        self::assertSame([$expectedCopyId], array_column($results, 'bookCopyId'));
        self::assertTrue($results[0]->offeredForLoan);
        self::assertSame('Near owner', $results[0]->ownerDisplayName);
        self::assertEqualsWithDelta(41.9028, $results[0]->approximateLatitude, 0.000000000001);
        self::assertEqualsWithDelta(12.5064, $results[0]->approximateLongitude, 0.000000000001);
    }

    /** @return list<\App\ReadModel\Discovery\Application\SearchBookCopies\DiscoveryBookCopyRecord> */
    private function results(
        string $currentUserId,
        ?string $query = null,
        int $radiusKm = 5,
        DiscoverySort $sort = DiscoverySort::DISTANCE,
    ): array
    {
        $results = $this->search->search(new DiscoverySearchCriteria(
            $currentUserId,
            $query,
            $radiusKm * 1000,
            $sort,
        ));
        self::assertNotNull($results);

        return $results;
    }

    private function insertUser(string $displayName, ?float $latitude = null, ?float $longitude = null): string
    {
        $id = $this->id('user');
        $location = null === $latitude || null === $longitude
            ? 'NULL'
            : 'ST_SetSRID(ST_MakePoint(:longitude, :latitude), 4326)::geography';
        $params = [
            'id' => $id,
            'email' => sprintf('user-%d@example.com', $this->sequence),
            'displayName' => $displayName,
            'createdAt' => '2026-09-01 08:00:00',
        ];

        if (null !== $latitude && null !== $longitude) {
            $params['latitude'] = $latitude;
            $params['longitude'] = $longitude;
        }

        $this->connection->executeStatement(
            <<<SQL
                INSERT INTO identity_user (
                    id, email, password_hash, display_name, roles, created_at, discovery_location
                ) VALUES (
                    :id, :email, 'password-hash', :displayName, '["ROLE_USER"]', :createdAt, {$location}
                )
                SQL,
            $params,
        );

        return $id;
    }

    /**
     * @param list<string> $authors
     * @param list<string> $categories
     */
    private function insertDiscoverableCopy(
        string $ownerId,
        string $title,
        array $authors,
        array $categories,
        ?int $publicationYear = null,
        bool $isPublished = true,
        string $availability = 'AVAILABLE',
        bool $offeredForLoan = true,
        ?string $archivedAt = null,
    ): string
    {
        $bookId = $this->insertBook($title, $authors, $categories, $publicationYear);

        return $this->insertCopy(
            $bookId,
            $ownerId,
            $isPublished,
            $availability,
            $offeredForLoan,
            $archivedAt,
        );
    }

    /**
     * @param list<string> $authors
     * @param list<string> $categories
     */
    private function insertBook(
        string $title,
        array $authors,
        array $categories,
        ?int $publicationYear = null,
    ): string
    {
        $bookId = $this->id('book');
        $this->connection->insert('library_book', [
            'id' => $bookId,
            'title' => $title,
            'publication_year' => $publicationYear,
            'isbn' => null,
            'created_at' => '2026-09-01 08:00:00',
            'updated_at' => '2026-09-01 08:00:00',
        ]);

        foreach ($authors as $position => $authorName) {
            $authorId = $this->id('author');
            $this->connection->insert('library_author', [
                'id' => $authorId,
                'name' => $authorName,
                'created_at' => '2026-09-01 08:00:00',
                'updated_at' => '2026-09-01 08:00:00',
            ]);
            $this->connection->insert('library_book_author', [
                'book_id' => $bookId,
                'author_id' => $authorId,
                'position' => $position,
            ]);
        }

        foreach ($categories as $categoryName) {
            $categoryId = $this->id('category');
            $this->connection->insert('library_category', [
                'id' => $categoryId,
                'name' => $categoryName,
                'slug' => sprintf('category-%d', $this->sequence),
                'active' => true,
            ], ['active' => Types::BOOLEAN]);
            $this->connection->insert('library_book_category', [
                'book_id' => $bookId,
                'category_id' => $categoryId,
            ]);
        }

        return $bookId;
    }

    private function insertCopy(
        string $bookId,
        string $ownerId,
        bool $isPublished = true,
        string $availability = 'AVAILABLE',
        bool $offeredForLoan = true,
        ?string $archivedAt = null,
    ): string
    {
        $copyId = $this->id('copy');
        $this->connection->insert('library_book_copy', [
            'id' => $copyId,
            'book_id' => $bookId,
            'owner_id' => $ownerId,
            'availability_status' => $availability,
            'offered_for_loan' => $offeredForLoan,
            'is_published' => $isPublished,
            'notes' => null,
            'archived_at' => $archivedAt,
            'created_at' => '2026-09-01 08:00:00',
            'updated_at' => '2026-09-01 08:00:00',
        ], [
            'offered_for_loan' => Types::BOOLEAN,
            'is_published' => Types::BOOLEAN,
        ]);

        return $copyId;
    }

    private function id(string $kind): string
    {
        ++$this->sequence;
        $group = match ($kind) {
            'user' => '0000',
            'book' => '0001',
            'author' => '0002',
            'category' => '0003',
            'copy' => '0004',
        };

        return sprintf('01990000-%s-7000-8000-%012d', $group, $this->sequence);
    }
}
