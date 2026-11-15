<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Identity\Domain\User\User;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class DiscoveryLocationApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $connection;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->connection->executeStatement('TRUNCATE TABLE identity_user CASCADE');
    }

    public function testLocationUsesApproximatePoint(): void
    {
        $rawLatitude = 41.9028;
        $rawLongitude = 12.4964;
        $token = $this->csrfToken();
        $this->register($token);

        $this->client->jsonRequest('PUT', '/api/profile/discovery-location', [
            'latitude' => $rawLatitude,
            'longitude' => $rawLongitude,
        ], ['HTTP_X_CSRF_TOKEN' => $token]);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $payload = $this->payload();
        self::assertSame(['discoveryLocation'], array_keys($payload));
        self::assertSame(['representativePoint'], array_keys($payload['discoveryLocation']));
        self::assertSame(['latitude', 'longitude'], array_keys($payload['discoveryLocation']['representativePoint']));
        $representative = $payload['discoveryLocation']['representativePoint'];
        self::assertNotEqualsWithDelta($rawLatitude, (float) $representative['latitude'], 0.0000001);
        self::assertNotEqualsWithDelta($rawLongitude, (float) $representative['longitude'], 0.0000001);

        $databasePoint = $this->connection->fetchAssociative(
            "SELECT ST_Y(discovery_location::geometry) AS latitude, ST_X(discovery_location::geometry) AS longitude FROM identity_user WHERE email = 'reader@example.com'",
        );
        self::assertIsArray($databasePoint);
        self::assertEqualsWithDelta((float) $representative['latitude'], (float) $databasePoint['latitude'], 0.000000000001);
        self::assertEqualsWithDelta((float) $representative['longitude'], (float) $databasePoint['longitude'], 0.000000000001);

        $this->client->request('GET', '/api/profile/discovery-location');
        self::assertResponseIsSuccessful();
        self::assertSame($payload, $this->payload());
    }

    private function csrfToken(): string
    {
        $this->client->request('GET', '/api/csrf-token');
        self::assertResponseIsSuccessful();

        return $this->payload()['token'];
    }

    private function register(string $token): void
    {
        $this->client->jsonRequest('POST', '/api/auth/register', [
            'email' => 'reader@example.com',
            'password' => 'correct horse battery staple',
            'displayName' => 'Reader',
        ], ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
