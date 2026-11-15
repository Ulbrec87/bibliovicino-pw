<?php

declare(strict_types=1);

namespace App\Tests\Library\Infrastructure\Http;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BookCopyManagementApiTest extends WebTestCase
{
    private const string CATEGORY = '0199a001-0000-7000-8000-000000000001';
    private KernelBrowser $client;
    private Connection $connection;
    private string $csrf;
    private string $ownerId;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->connection->executeStatement('TRUNCATE TABLE library_book_copy, library_book_category, library_book_author, library_book, library_category, library_author, identity_user CASCADE');
        $this->connection->insert('library_category', ['id' => self::CATEGORY, 'name' => 'Narrativa', 'slug' => 'narrativa', 'active' => true]);
        $this->ownerId = $this->register('owner');
    }

    public function testVisitorsCannotManageOtherUsersCopies(): void
    {
        $private = $this->createCopy();
        $published = $this->createCopy(true);
        $this->logout();
        $this->register('visitor');
        $this->client->request('GET', $this->url($private));
        self::assertResponseStatusCodeSame(404);
        foreach ([$private, $published] as $copy) {
            foreach (['notes', 'publish', 'unpublish', 'archive'] as $action) {
                $this->mutate($copy, $action, ['notes' => 'forged']);
                self::assertResponseStatusCodeSame(404);
            }
        }
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM library_book_copy WHERE archived_at IS NULL'));
        self::assertNull($this->connection->fetchOne('SELECT notes FROM library_book_copy WHERE id = :id', ['id' => $private]));
    }

    private function register(string $name): string
    {
        $this->client->request('GET', '/api/csrf-token');
        $this->csrf = $this->payload()['token'];
        $this->client->jsonRequest('POST', '/api/auth/register', ['email' => $name.'@example.com', 'password' => 'correct horse battery staple', 'displayName' => $name], ['HTTP_X_CSRF_TOKEN' => $this->csrf]);
        self::assertResponseStatusCodeSame(201);
        return $this->payload()['user']['id'];
    }

    private function logout(): void
    {
        $this->client->jsonRequest('POST', '/api/auth/logout', [], ['HTTP_X_CSRF_TOKEN' => $this->csrf]);
        self::assertResponseStatusCodeSame(204);
    }

    private function createCopy(bool $publish = false): string
    {
        if ($publish) {
            $this->setLocation($this->ownerId);
        }
        $this->client->jsonRequest('POST', '/api/library/book-copies', [
            'newBook' => ['title' => 'Libro', 'authors' => ['Autore'], 'publicationYear' => null, 'categoryIds' => [self::CATEGORY], 'isbn' => null],
            'notes' => null, 'publish' => $publish,
        ], ['HTTP_X_CSRF_TOKEN' => $this->csrf]);
        self::assertResponseStatusCodeSame(201);
        return $this->payload()['bookCopy']['id'];
    }

    private function mutate(string $id, string $action, array $input = []): void
    {
        $this->client->jsonRequest($action === 'notes' ? 'PATCH' : 'POST', $this->url($id).'/'.$action, $input, ['HTTP_X_CSRF_TOKEN' => $this->csrf]);
    }

    private function url(string $id): string
    {
        return '/api/library/book-copies/'.$id;
    }

    private function setLocation(string $id): void
    {
        $this->connection->executeStatement('UPDATE identity_user SET discovery_location = ST_SetSRID(ST_MakePoint(12.5, 41.9), 4326)::geography WHERE id = :id', ['id' => $id]);
    }

    private function payload(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
