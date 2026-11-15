<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProfileApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $db;
    private string $csrf;
    private string $userId;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->db = self::getContainer()->get(Connection::class);
        self::getContainer()->get('cache.rate_limiter')->clear();
        $this->db->executeStatement('TRUNCATE TABLE identity_user CASCADE');
        $this->client->request('GET', '/api/csrf-token');
        $this->csrf = $this->payload()['token'];
        $this->client->jsonRequest('POST', '/api/auth/register', [
            'email' => 'reader@example.com', 'password' => 'correct horse battery staple', 'displayName' => 'Reader',
        ], ['HTTP_X_CSRF_TOKEN' => $this->csrf]);
        self::assertResponseStatusCodeSame(201);
        $this->userId = $this->payload()['user']['id'];
    }

    public function testProfileUpdatePreservesAccountData(): void
    {
        $this->db->executeStatement(
            "UPDATE identity_user SET roles = :roles, discovery_location = ST_SetSRID(ST_MakePoint(12.5, 41.9), 4326)::geography WHERE id = :id",
            ['roles' => '["ROLE_USER","ROLE_ADMIN"]', 'id' => $this->userId],
        );
        $this->client->jsonRequest('POST', '/api/auth/logout', [], ['HTTP_X_CSRF_TOKEN' => $this->csrf]);
        $this->client->request('GET', '/api/csrf-token');
        $this->csrf = $this->payload()['token'];
        $this->client->jsonRequest('POST', '/api/auth/login', [
            'email' => 'reader@example.com', 'password' => 'correct horse battery staple',
        ], ['HTTP_X_CSRF_TOKEN' => $this->csrf]);
        self::assertResponseStatusCodeSame(200);
        $before = $this->db->fetchAssociative('SELECT * FROM identity_user WHERE id = ?', [$this->userId]);
        $sessionId = $this->client->getCookieJar()->all()[0]->getValue();
        $name = "\u{00a0}Élodie  読者 42\u{2003}";

        $this->patch(['displayName' => $name]);
        self::assertResponseStatusCodeSame(200);
        self::assertStringContainsString('no-store', $this->client->getResponse()->headers->get('Cache-Control'));
        $updated = $this->payload();
        self::assertSame([
            'user' => [
                'id' => $this->userId, 'email' => 'reader@example.com',
                'displayName' => 'Élodie  読者 42', 'roles' => ['ROLE_USER', 'ROLE_ADMIN'],
            ],
        ], $updated);
        self::assertSame($sessionId, $this->client->getCookieJar()->all()[0]->getValue());
        $after = $this->db->fetchAssociative('SELECT * FROM identity_user WHERE id = ?', [$this->userId]);
        $before['display_name'] = 'Élodie  読者 42';
        self::assertSame($before, $after);

        $this->client->request('GET', '/api/auth/me');
        self::assertResponseStatusCodeSame(200);
        self::assertSame($updated, $this->payload());
        $this->patch(['displayName' => 'Élodie  読者 42']);
        self::assertResponseStatusCodeSame(200);
        self::assertSame($updated, $this->payload());
        self::assertSame($after, $this->db->fetchAssociative('SELECT * FROM identity_user WHERE id = ?', [$this->userId]));
        self::assertSame($sessionId, $this->client->getCookieJar()->all()[0]->getValue());
    }

    private function patch(array $payload): void
    {
        $this->client->jsonRequest('PATCH', '/api/profile', $payload, ['HTTP_X_CSRF_TOKEN' => $this->csrf]);
    }

    private function payload(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
