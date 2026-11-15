<?php

declare(strict_types=1);

namespace App\Tests\ReadModel\Discovery\Infrastructure\Http;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class DiscoverySearchApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $connection;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->connection->executeStatement(
            'TRUNCATE TABLE library_book_copy, library_book_category, library_book_author, library_book, library_category, library_author, identity_user CASCADE',
        );
    }

    public function testSearchUsesDefaultsAndProtectsPrivacy(): void
    {
        $currentUserId = $this->register();
        $this->setLocation($currentUserId, 41.9028, 12.4964);
        $ownerId = '01990000-1000-7000-8000-000000000001';
        $copyId = '01990000-1004-7000-8000-000000000001';
        $this->insertOwnerAndCopy($ownerId, $copyId, 41.9028, 12.5181);

        $this->client->request('GET', '/api/discovery/book-copies?q=dUnE');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('private', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $payload = $this->payload();
        self::assertSame([
            'radiusKm' => 5,
            'sort' => 'relevance',
            'returnedCount' => 1,
        ], $payload['meta']);
        self::assertCount(1, $payload['results']);
        $result = $payload['results'][0];
        self::assertSame($copyId, $result['bookCopyId']);
        self::assertSame([
            'title' => 'Dune',
            'authors' => ['Frank Herbert'],
            'publicationYear' => 1965,
            'categories' => ['Fantascienza'],
        ], $result['book']);
        self::assertSame(['id' => $ownerId, 'displayName' => 'Nearby owner'], $result['owner']);
        self::assertSame(['kind' => 'around_km', 'kilometres' => 2], $result['approximateDistance']);
        self::assertSame(['loan'], $result['offerModes']);
        self::assertEqualsWithDelta(41.9028, (float) $result['approximatePoint']['latitude'], 0.000000000001);
        self::assertEqualsWithDelta(12.5181, (float) $result['approximatePoint']['longitude'], 0.000000000001);

        $json = (string) $this->client->getResponse()->getContent();
        foreach (['email', 'address', 'distanceMetres', 'exactDistance', 'longitudeRaw', 'latitudeRaw'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $json);
        }
    }

    private function register(): string
    {
        $this->client->request('GET', '/api/csrf-token');
        $token = $this->payload()['token'];
        $this->client->jsonRequest('POST', '/api/auth/register', [
            'email' => 'reader@example.com',
            'password' => 'correct horse battery staple',
            'displayName' => 'Reader',
        ], ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return $this->payload()['user']['id'];
    }

    private function setLocation(string $userId, float $latitude, float $longitude): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                UPDATE identity_user
                SET discovery_location = ST_SetSRID(ST_MakePoint(:longitude, :latitude), 4326)::geography
                WHERE id = :id
                SQL,
            ['id' => $userId, 'latitude' => $latitude, 'longitude' => $longitude],
        );
    }

    private function insertOwnerAndCopy(string $ownerId, string $copyId, float $latitude, float $longitude): void
    {
        $authorId = '01990000-1002-7000-8000-000000000001';
        $categoryId = '01990000-1003-7000-8000-000000000001';
        $bookId = '01990000-1001-7000-8000-000000000001';
        $this->connection->executeStatement(
            <<<'SQL'
                INSERT INTO identity_user (
                    id, email, password_hash, display_name, roles, created_at, discovery_location
                ) VALUES (
                    :id, 'owner@example.com', 'password-hash', 'Nearby owner', '["ROLE_USER"]',
                    '2026-09-01 08:00:00',
                    ST_SetSRID(ST_MakePoint(:longitude, :latitude), 4326)::geography
                )
                SQL,
            ['id' => $ownerId, 'latitude' => $latitude, 'longitude' => $longitude],
        );
        $this->connection->insert('library_author', [
            'id' => $authorId,
            'name' => 'Frank Herbert',
            'created_at' => '2026-09-01 08:00:00',
            'updated_at' => '2026-09-01 08:00:00',
        ]);
        $this->connection->insert('library_category', [
            'id' => $categoryId,
            'name' => 'Fantascienza',
            'slug' => 'fantascienza',
            'active' => true,
        ], ['active' => Types::BOOLEAN]);
        $this->connection->insert('library_book', [
            'id' => $bookId,
            'title' => 'Dune',
            'publication_year' => 1965,
            'isbn' => null,
            'created_at' => '2026-09-01 08:00:00',
            'updated_at' => '2026-09-01 08:00:00',
        ]);
        $this->connection->insert('library_book_author', [
            'book_id' => $bookId,
            'author_id' => $authorId,
            'position' => 0,
        ]);
        $this->connection->insert('library_book_category', [
            'book_id' => $bookId,
            'category_id' => $categoryId,
        ]);
        $this->connection->insert('library_book_copy', [
            'id' => $copyId,
            'book_id' => $bookId,
            'owner_id' => $ownerId,
            'availability_status' => 'AVAILABLE',
            'offered_for_loan' => true,
            'is_published' => true,
            'notes' => null,
            'archived_at' => null,
            'created_at' => '2026-09-01 08:00:00',
            'updated_at' => '2026-09-01 08:00:00',
        ], [
            'offered_for_loan' => Types::BOOLEAN,
            'is_published' => Types::BOOLEAN,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
