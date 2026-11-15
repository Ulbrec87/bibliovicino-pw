<?php

declare(strict_types=1);

namespace App\Tests\ReadModel\AdminStatistics;

use App\Infrastructure\Demo\DemoDatasetSeeder;
use App\Shared\Application\Clock;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class AdminStatisticsApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $connection;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->connection->executeStatement(
            'TRUNCATE TABLE loan_request, library_book_copy, library_book_category, library_book_author, library_book, library_category, library_author, identity_user CASCADE',
        );
        $this->insertReferenceCategories();
        self::getContainer()->get(DemoDatasetSeeder::class)->seed();
    }

    public function testStatisticsRejectRegularUsers(): void
    {
        $this->login(DemoDatasetSeeder::PRIMARY_EMAIL);
        $this->client->request('GET', '/api/admin/statistics');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertResponseFormatSame('json');
        self::assertSame('access_denied', $this->payload()['error']['code']);
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
    }

    public function testAdminReceivesAggregateStatistics(): void
    {
        $this->login(DemoDatasetSeeder::ADMIN_EMAIL);
        $this->client->request('GET', '/api/admin/statistics');

        self::assertResponseIsSuccessful();
        self::assertResponseFormatSame('json');
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $statistics = $this->payload()['statistics'];
        self::assertSame(10, $statistics['registeredUsers']);
        self::assertSame(28, $statistics['publishedCopies']);
        self::assertSame(12, $statistics['loanRequests']);
        self::assertSame(6, $statistics['completedLoans']);
        self::assertCount(8, $statistics['weeklyLoanRequests']);
        self::assertSame(12, array_sum(array_column($statistics['weeklyLoanRequests'], 'count')));
        self::assertSame(0, $statistics['weeklyLoanRequests'][7]['count']);
        $currentWeekStart = self::getContainer()->get(Clock::class)->now()->modify('monday this week')->setTime(0, 0);
        self::assertSame($currentWeekStart->format('Y-m-d'), $statistics['weeklyLoanRequests'][7]['weekStart']);
        self::assertStringNotContainsString('@bibliovicino.test', (string) $this->client->getResponse()->getContent());
    }

    private function login(string $email): void
    {
        $this->client->request('GET', '/api/csrf-token');
        self::assertResponseIsSuccessful();
        $token = $this->payload()['token'];

        $this->client->jsonRequest('POST', '/api/auth/login', [
            'email' => $email,
            'password' => DemoDatasetSeeder::PASSWORD,
        ], ['HTTP_X_CSRF_TOKEN' => $token]);
        self::assertResponseIsSuccessful();
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function insertReferenceCategories(): void
    {
        $categories = [
            'narrativa' => 'Narrativa',
            'fantascienza' => 'Fantascienza',
            'fantasy' => 'Fantasy',
            'classici' => 'Classici',
            'giallo-e-thriller' => 'Giallo e thriller',
            'horror' => 'Horror',
            'saggistica' => 'Saggistica',
            'informatica' => 'Informatica',
        ];
        $position = 1;
        foreach ($categories as $slug => $name) {
            $this->connection->insert('library_category', [
                'id' => sprintf('0199a001-0000-7000-8000-%012d', $position++),
                'name' => $name,
                'slug' => $slug,
                'active' => true,
            ]);
        }
    }
}
