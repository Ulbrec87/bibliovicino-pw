<?php

declare(strict_types=1);

namespace App\Tests\Library\Infrastructure\Http;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class LibraryApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $connection;
    private string $csrf;
    private string $userId;
    private string $categoryId = '0199a001-0000-7000-8000-000000000001';

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->connection->executeStatement('TRUNCATE TABLE library_book_copy, library_book_category, library_book_author, library_book, library_category, library_author, identity_user CASCADE');
        $this->connection->insert('library_category', ['id' => $this->categoryId, 'name' => 'Narrativa', 'slug' => 'narrativa', 'active' => true]);
        $this->client->request('GET', '/api/csrf-token');
        $this->csrf = $this->payload()['token'];
        $this->client->jsonRequest('POST', '/api/auth/register', [
            'email' => 'reader@example.com', 'password' => 'correct horse battery staple', 'displayName' => 'Reader',
        ], ['HTTP_X_CSRF_TOKEN' => $this->csrf]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->userId = $this->payload()['user']['id'];
    }

    public function testUserCanCreateAndListPrivateCopy(): void
    {
        $this->client->request('GET', '/api/library/categories');
        self::assertSame([['id' => $this->categoryId, 'name' => 'Narrativa', 'slug' => 'narrativa']], $this->payload()['categories']);

        $this->post($this->newCopy('Dune', 'Frank Herbert', '0-441-17271-7'));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $first = $this->payload()['bookCopy'];
        self::assertFalse($first['isPublished']);

        $this->client->request('GET', '/api/library/books?q=dUnE&page=1');
        self::assertResponseIsSuccessful();
        self::assertSame('Dune', $this->payload()['results'][0]['title']);
        $this->client->request('GET', '/api/library/books?q=HERBERT&page=1');
        self::assertCount(1, $this->payload()['results']);
        $this->client->request('GET', '/api/library/books?q=9780441172719&page=1');
        self::assertSame($first['bookId'], $this->payload()['results'][0]['id']);
        $this->client->request('GET', '/api/library/books?q=0-441-17271-7&page=1');
        self::assertSame($first['bookId'], $this->payload()['results'][0]['id']);

        $this->post(['bookId' => $first['bookId'], 'notes' => null, 'publish' => false]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame(1, $this->countRows('library_book'));
        self::assertSame(2, $this->countRows('library_book_copy'));

        $this->client->request('GET', '/api/library/my-book-copies');
        $copies = $this->payload()['bookCopies'];
        self::assertCount(2, $copies);
        self::assertSame($first['bookId'], $this->connection->fetchOne('SELECT book_id::text FROM library_book_copy WHERE id = :id', ['id' => $copies[0]['id']]));
        self::assertSame([], $copies[0]['offerModes']);
        self::assertFalse($copies[0]['isPublished']);
    }

    public function testExistingIsbnAndAuthorAreReused(): void
    {
        $this->post($this->newCopy('Dune', 'Frank Herbert', '0-441-17271-7'));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $originalBookId = $this->payload()['bookCopy']['bookId'];
        $this->post($this->newCopy('Another title', 'Frank Herbert', '9780441172719'));
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('book_already_exists', $this->payload()['error']['code']);
        self::assertSame($originalBookId, $this->payload()['error']['book']['id']);
        self::assertSame(1, $this->countRows('library_book'));
        self::assertSame(1, $this->countRows('library_book_copy'));

        $this->post($this->newCopy('New edition', '  frank   herbert '));
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame(1, $this->countRows('library_author'));
    }

    public function testPublishedCopyRequiresLocationAndAppearsInSearch(): void
    {
        $input = $this->newCopy('Loan title', 'Loan Author');
        $input['publish'] = true;
        $this->post($input);
        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        self::assertSame('discovery_location_required', $this->payload()['error']['code']);
        self::assertSame(0, $this->countRows('library_book'));
        $this->setLocation($this->userId, 41.9, 12.5);
        $this->post($input);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertTrue($this->payload()['bookCopy']['isPublished']);
        $ownerCopyId = $this->payload()['bookCopy']['id'];

        $this->client->jsonRequest('POST', '/api/auth/logout', [], ['HTTP_X_CSRF_TOKEN' => $this->csrf]);
        $this->client->request('GET', '/api/csrf-token');
        $this->csrf = $this->payload()['token'];
        $this->client->jsonRequest('POST', '/api/auth/register', [
            'email' => 'searcher@example.com', 'password' => 'correct horse battery staple', 'displayName' => 'Searcher',
        ], ['HTTP_X_CSRF_TOKEN' => $this->csrf]);
        $searcherId = $this->payload()['user']['id'];
        $this->setLocation($searcherId, 41.9, 12.5);
        $this->client->request('GET', '/api/discovery/book-copies?q=Loan');
        self::assertResponseIsSuccessful();
        self::assertSame($ownerCopyId, $this->payload()['results'][0]['bookCopyId']);
        self::assertSame(['loan'], $this->payload()['results'][0]['offerModes']);
    }

    private function newCopy(string $title, string $author, ?string $isbn = null): array
    {
        return [
            'newBook' => ['title' => $title, 'authors' => [$author], 'publicationYear' => null, 'categoryIds' => [$this->categoryId], 'isbn' => $isbn],
            'notes' => null, 'publish' => false,
        ];
    }

    private function post(array $input): void
    {
        $this->client->jsonRequest('POST', '/api/library/book-copies', $input, ['HTTP_X_CSRF_TOKEN' => $this->csrf]);
    }

    private function setLocation(string $userId, float $latitude, float $longitude): void
    {
        $this->connection->executeStatement('UPDATE identity_user SET discovery_location = ST_SetSRID(ST_MakePoint(:longitude, :latitude), 4326)::geography WHERE id = :id', ['id' => $userId, 'latitude' => $latitude, 'longitude' => $longitude]);
    }

    private function countRows(string $table): int
    {
        return (int) $this->connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s', $table));
    }

    private function payload(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
