<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use Doctrine\DBAL\Connection;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class IdentityAuthTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        self::getContainer()->get(Connection::class)->executeStatement('TRUNCATE TABLE identity_user RESTART IDENTITY CASCADE');
        $rateLimiterCache = self::getContainer()->get('cache.rate_limiter');
        assert($rateLimiterCache instanceof CacheItemPoolInterface);
        $rateLimiterCache->clear();
    }

    public function testWritesRequireCsrfToken(): void
    {
        $this->client->jsonRequest('POST', '/api/auth/register', $this->validRegistration());

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('invalid_csrf_token', $this->payload()['error']['code']);
    }

    public function testRegistrationAuthenticatesAndRenewsSession(): void
    {
        $token = $this->csrfToken();
        $anonymousSessionId = $this->sessionId();

        $this->register($token);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $user = $this->payload()['user'];
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $user['id']);
        unset($user['id']);
        self::assertSame([
            'email' => 'reader@example.com',
            'displayName' => 'Reader',
            'roles' => ['ROLE_USER'],
        ], $user);
        self::assertNotSame($anonymousSessionId, $this->sessionId());

        $this->client->request('GET', '/api/auth/me');
        self::assertResponseIsSuccessful();
        self::assertSame('reader@example.com', $this->payload()['user']['email']);
    }

    public function testRegistrationCannotGrantAdminRole(): void
    {
        $payload = $this->validRegistration();
        $payload['roles'] = ['ROLE_ADMIN'];

        $this->client->jsonRequest('POST', '/api/auth/register', $payload, [
            'HTTP_X_CSRF_TOKEN' => $this->csrfToken(),
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertSame(['ROLE_USER'], $this->payload()['user']['roles']);

        $roles = self::getContainer()->get(Connection::class)->fetchOne('SELECT roles FROM identity_user WHERE email = ?', ['reader@example.com']);
        self::assertSame(['ROLE_USER'], json_decode((string) $roles, true, 512, JSON_THROW_ON_ERROR));
    }

    public function testLoginChecksCredentials(): void
    {
        $token = $this->csrfToken();
        $this->register($token);
        $this->logout($token);
        $token = $this->csrfToken();

        $this->client->jsonRequest('POST', '/api/auth/login', [
            'email' => 'reader@example.com',
            'password' => 'wrong-password',
        ], ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame('invalid_credentials', $this->payload()['error']['code']);

        $this->client->jsonRequest('POST', '/api/auth/login', [
            'email' => 'READER@example.com',
            'password' => 'correct horse battery staple',
        ], ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseIsSuccessful();
        self::assertSame('Reader', $this->payload()['user']['displayName']);

        $this->client->request('GET', '/api/auth/me');
        self::assertResponseIsSuccessful();
    }

    public function testLogoutInvalidatesSessionAndToken(): void
    {
        $token = $this->csrfToken();
        $this->register($token);

        $this->client->request('GET', '/api/auth/logout');
        self::assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
        self::assertResponseFormatSame('json');
        self::assertSame('method_not_allowed', $this->payload()['error']['code']);

        $this->client->jsonRequest('POST', '/api/auth/logout');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->logout($token);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertEmpty($this->client->getCookieJar()->all());

        $this->client->request('GET', '/api/auth/me');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $this->client->jsonRequest('POST', '/api/auth/login', [
            'email' => 'reader@example.com',
            'password' => 'correct horse battery staple',
        ], ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertSame('invalid_csrf_token', $this->payload()['error']['code']);
    }

    private function csrfToken(): string
    {
        $this->client->request('GET', '/api/csrf-token');
        self::assertResponseIsSuccessful();

        return $this->payload()['token'];
    }

    private function register(string $token): void
    {
        $this->client->jsonRequest('POST', '/api/auth/register', $this->validRegistration(), [
            'HTTP_X_CSRF_TOKEN' => $token,
        ]);
    }

    private function logout(string $token): void
    {
        $this->client->jsonRequest('POST', '/api/auth/logout', [], [
            'HTTP_X_CSRF_TOKEN' => $token,
        ]);
    }

    /** @return array{email: string, password: string, displayName: string} */
    private function validRegistration(): array
    {
        return [
            'email' => 'Reader@Example.com',
            'password' => 'correct horse battery staple',
            'displayName' => 'Reader',
        ];
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function sessionId(): string
    {
        return $this->client->getCookieJar()->all()[0]->getValue() ?? '';
    }
}
