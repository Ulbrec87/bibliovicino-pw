<?php

declare(strict_types=1);

namespace App\Tests\Library\Infrastructure\Http;

use App\Library\Application\Cover\ExternalCoverReferences;
use App\Library\Domain\BookCopy\BookCopy;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class BookCopyCoverApiTest extends WebTestCase
{
    private const string CATEGORY = '0199a001-0000-7000-8000-000000000001';
    private KernelBrowser $client;
    private Connection $db;
    private string $csrf;
    private string $owner;
    private array $paths = [];

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->db = self::getContainer()->get(Connection::class);
        $this->db->executeStatement('TRUNCATE library_book_copy, library_book_category, library_book_author, library_book, library_category, library_author, identity_user CASCADE');
        $this->db->insert('library_category', ['id' => self::CATEGORY, 'name' => 'Narrativa', 'slug' => 'narrativa', 'active' => true]);
        $this->owner = $this->register('cover-owner');
    }

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) { if (is_file($path)) unlink($path); }
        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('coverCommandIds')]
    public function testCoverAccessRequiresPermissionAndCsrf(bool $uppercaseId): void
    {
        $copy = $this->createCopy();
        if ($uppercaseId) {
            $copy = strtoupper($copy);
        }
        $this->upload($copy);
        $this->assertReadable($copy);
        $this->client->request('DELETE', $this->url($copy).'/cover');
        self::assertResponseStatusCodeSame(403);
        $this->logout();
        $this->client->request('GET', $this->url($copy).'/cover/thumbnail');
        self::assertResponseStatusCodeSame(401);
        $this->register('cover-visitor');
        $this->client->request('GET', $this->url($copy).'/cover/thumbnail');
        self::assertResponseStatusCodeSame(404);
        foreach (['POST', 'DELETE', 'PUT'] as $method) {
            if ($method === 'POST') $this->upload($copy);
            else $this->client->jsonRequest($method, $this->url($copy).'/cover', ['source' => 'external', 'reference' => self::getContainer()->get(ExternalCoverReferences::class)->issue(123)], ['HTTP_X_CSRF_TOKEN' => $this->csrf]);
            self::assertResponseStatusCodeSame(404);
        }
        $this->db->update('library_book_copy', ['offered_for_loan' => true, 'is_published' => true, 'availability_status' => 'UNAVAILABLE'], ['id' => $copy]);
        $this->assertReadable($copy);
        $this->client->request('GET', $this->url($copy));
        self::assertArrayNotHasKey('reference', $this->payload()['bookCopy']['cover']);
        $this->db->update('library_book_copy', ['is_published' => 'false'], ['id' => $copy]);
        $this->client->request('GET', $this->url($copy).'/cover/large');
        self::assertResponseStatusCodeSame(404);
        $this->db->update('library_book_copy', ['archived_at' => '2026-10-21 10:00:00'], ['id' => $copy]);
        $this->logout();
        $this->loginOwner();
        $this->client->request('GET', $this->url($copy).'/cover/thumbnail');
        self::assertResponseStatusCodeSame(404);
        $this->upload($copy);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('uploaded', $this->stored($copy)['cover_source']);
    }

    public static function coverCommandIds(): iterable
    {
        yield 'canonical' => [false];
        yield 'uppercase' => [true];
    }

    private function assertReadable(string $copy): void
    {
        foreach (['thumbnail', 'large'] as $variant) {
            $this->client->request('GET', $this->url($copy).'/cover/'.$variant);
            self::assertResponseIsSuccessful();
            self::assertSame('image/webp', $this->client->getResponse()->headers->get('Content-Type'));
            self::assertStringContainsString('no-store', $this->client->getResponse()->headers->get('Cache-Control'));
            $blob = $this->client->getInternalResponse()->getContent();
            self::assertStringStartsWith('RIFF', $blob);
        }
    }

    private function imagePath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'cover-api-');
        copy(__DIR__.'/../../../Fixtures/covers/transparent.png', $path);
        $this->paths[] = $path;
        return $path;
    }
    private function upload(string $id, ?string $blob = null): void
    {
        $path = $this->imagePath(); if (null !== $blob) file_put_contents($path, $blob);
        $this->client->request('POST', $this->url($id).'/cover', files: ['image' => new UploadedFile($path, '../../forged.jpg', 'image/jpeg', null, true)], server: ['HTTP_X_CSRF_TOKEN' => $this->csrf]);
    }
    private function createCopy(?array $cover = null, int $status = 201): string
    {
        $this->client->jsonRequest('POST', '/api/library/book-copies', ['newBook' => ['title' => 'Libro', 'authors' => ['Autore'], 'publicationYear' => null, 'categoryIds' => [self::CATEGORY], 'isbn' => null], 'notes' => null, 'publish' => false, 'cover' => $cover], ['HTTP_X_CSRF_TOKEN' => $this->csrf]);
        self::assertResponseStatusCodeSame($status);
        return $this->payload()['bookCopy']['id'] ?? '';
    }
    private function register(string $name): string
    {
        $this->client->request('GET', '/api/csrf-token'); $this->csrf = $this->payload()['token'];
        $this->client->jsonRequest('POST', '/api/auth/register', ['email' => $name.'@example.com', 'password' => 'correct horse battery staple', 'displayName' => $name], ['HTTP_X_CSRF_TOKEN' => $this->csrf]);
        self::assertResponseStatusCodeSame(201); return $this->payload()['user']['id'];
    }
    private function logout(): void { $this->client->jsonRequest('POST', '/api/auth/logout', [], ['HTTP_X_CSRF_TOKEN' => $this->csrf]); }
    private function loginOwner(): void
    {
        $this->client->request('GET', '/api/csrf-token'); $this->csrf = $this->payload()['token'];
        $this->client->jsonRequest('POST', '/api/auth/login', ['email' => 'cover-owner@example.com', 'password' => 'correct horse battery staple'], ['HTTP_X_CSRF_TOKEN' => $this->csrf]);
        self::assertResponseIsSuccessful();
    }
    private function url(string $id): string { return '/api/library/book-copies/'.$id; }
    private function stored(string $id): array { return $this->db->fetchAssociative('SELECT cover_source, cover_reference FROM library_book_copy WHERE id = :id', ['id' => $id]); }
    private function payload(): array { return json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR); }
}
