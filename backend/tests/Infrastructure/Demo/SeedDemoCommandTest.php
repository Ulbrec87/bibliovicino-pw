<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Demo;

use App\Identity\Domain\User\UserRepository;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Infrastructure\Demo\DemoDatasetSeeder;
use App\Infrastructure\Demo\SeedDemoCommand;
use App\Library\Application\Cover\CoverStorage;
use App\Library\Infrastructure\Cover\LocalCoverStorage;
use App\ReadModel\Discovery\Application\SearchBookCopies\SearchBookCopiesCommand;
use App\ReadModel\Discovery\Application\SearchBookCopies\SearchBookCopiesHandler;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Uid\Uuid;

final class SeedDemoCommandTest extends KernelTestCase
{
    private Connection $connection;
    private DemoDatasetSeeder $seeder;
    private LocalCoverStorage $coverStorage;
    private string $coverRoot;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->connection = $container->get(Connection::class);
        $this->coverRoot = sys_get_temp_dir().'/demo-covers-'.Uuid::v7()->toRfc4122();
        $this->coverStorage = new LocalCoverStorage($this->coverRoot);
        $container->set(CoverStorage::class, $this->coverStorage);
        $this->seeder = $container->get(DemoDatasetSeeder::class);
        $this->truncateDataset();
        $this->insertReferenceCategories();
    }

    protected function tearDown(): void
    {
        foreach ($this->coverStorage->candidates() as $asset) {
            $this->coverStorage->delete($asset['copyId'], $asset['reference']);
        }
        foreach (glob($this->coverRoot.'/*', GLOB_ONLYDIR) ?: [] as $directory) {
            rmdir($directory);
        }
        if (is_dir($this->coverRoot)) {
            rmdir($this->coverRoot);
        }
        parent::tearDown();
    }

    public function testSeedCreatesDemoDataset(): void
    {
        $tester = new CommandTester(new SeedDemoCommand($this->seeder, 'test'));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame(10, $this->countRows('identity_user'));
        self::assertSame(23, $this->countRows('library_book'));
        self::assertSame(30, $this->countRows('library_book_copy'));
        self::assertSame(12, $this->countRows('loan_request'));
        $this->assertStoredCovers();

        $users = self::getContainer()->get(UserRepository::class);
        $demo = $users->findByEmail(DemoDatasetSeeder::PRIMARY_EMAIL);
        $admin = $users->findByEmail(DemoDatasetSeeder::ADMIN_EMAIL);
        self::assertNotNull($demo);
        self::assertNotNull($demo->discoveryLocation());
        self::assertSame(['ROLE_USER'], $demo->roles());
        self::assertNotNull($admin);
        self::assertSame(['ROLE_USER', 'ROLE_ADMIN'], $admin->roles());

        $hasher = self::getContainer()->get(PasswordHasherFactoryInterface::class)->getPasswordHasher(SecurityUser::class);
        self::assertTrue($hasher->verify($demo->passwordHash(), DemoDatasetSeeder::PASSWORD));
        self::assertTrue($hasher->verify($admin->passwordHash(), DemoDatasetSeeder::PASSWORD));

        $search = self::getContainer()->get(SearchBookCopiesHandler::class);
        foreach ([1 => 7, 2 => 12, 5 => 15, 10 => 18] as $radius => $expectedCount) {
            self::assertSame($expectedCount, $search->handle(new SearchBookCopiesCommand(
                $demo->id()->toString(),
                radiusKm: $radius,
            ))->returnedCount());
        }
    }

    public function testSeedIsIdempotent(): void
    {
        $first = new CommandTester(new SeedDemoCommand($this->seeder, 'test'));
        self::assertSame(Command::SUCCESS, $first->execute([]));
        $countsBefore = $this->datasetCounts();
        $coversBefore = $this->connection->fetchAllAssociative('SELECT id::text, cover_source, cover_reference FROM library_book_copy ORDER BY id');

        $before = $this->connection->fetchAllAssociative(
            'SELECT id::text AS id, email, created_at::text AS created_at, roles::text AS roles FROM identity_user ORDER BY email',
        );
        $requestDatesBefore = $this->connection->fetchAllAssociative(
            'SELECT id::text AS id, requested_at::text AS requested_at, updated_at::text AS updated_at FROM loan_request ORDER BY id',
        );
        $second = new CommandTester(new SeedDemoCommand($this->seeder, 'test'));
        self::assertSame(Command::SUCCESS, $second->execute([]));

        self::assertStringContainsString('already present', $second->getDisplay());
        self::assertSame($countsBefore, $this->datasetCounts());
        self::assertSame($coversBefore, $this->connection->fetchAllAssociative('SELECT id::text, cover_source, cover_reference FROM library_book_copy ORDER BY id'));
        self::assertCount(30, iterator_to_array($this->coverStorage->candidates()));
        self::assertSame($before, $this->connection->fetchAllAssociative(
            'SELECT id::text AS id, email, created_at::text AS created_at, roles::text AS roles FROM identity_user ORDER BY email',
        ));
        self::assertSame($requestDatesBefore, $this->connection->fetchAllAssociative(
            'SELECT id::text AS id, requested_at::text AS requested_at, updated_at::text AS updated_at FROM loan_request ORDER BY id',
        ));
    }

    private function assertStoredCovers(): void
    {
        $rows = $this->connection->fetchAllAssociative('SELECT id::text, cover_source, cover_reference FROM library_book_copy ORDER BY id');
        self::assertCount(30, $rows);
        self::assertSame(['uploaded'], array_values(array_unique(array_column($rows, 'cover_source'))));
        self::assertCount(30, array_unique(array_column($rows, 'cover_reference')));

        $assets = iterator_to_array($this->coverStorage->candidates());
        self::assertCount(30, $assets);
        $references = array_column($rows, 'cover_reference');
        $storedReferences = array_column($assets, 'reference');
        sort($references);
        sort($storedReferences);
        self::assertSame($references, $storedReferences);

        foreach ($rows as $row) {
            foreach (['thumbnail', 'large'] as $variant) {
                $stream = $this->coverStorage->open($row['id'], $row['cover_reference'], $variant);
                self::assertIsResource($stream);
                fclose($stream);
            }
        }
    }

    /** @return list<int> */
    private function datasetCounts(): array
    {
        return array_map($this->countRows(...), [
            'identity_user',
            'library_category',
            'library_author',
            'library_book',
            'library_book_copy',
            'library_book_author',
            'loan_request',
        ]);
    }

    private function countRows(string $table): int
    {
        return (int) $this->connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s', $table));
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

    private function truncateDataset(): void
    {
        $this->connection->executeStatement(
            'TRUNCATE TABLE library_book_copy, library_book_category, library_book_author, library_book, library_category, library_author, identity_user CASCADE',
        );
    }
}
