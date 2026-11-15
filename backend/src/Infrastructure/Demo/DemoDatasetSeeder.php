<?php

declare(strict_types=1);

namespace App\Infrastructure\Demo;

use App\Identity\Application\RegisterUser\PasswordHasher;
use App\Identity\Application\SetDiscoveryLocation\SetDiscoveryLocationCommand;
use App\Identity\Application\SetDiscoveryLocation\SetDiscoveryLocationHandler;
use App\Identity\Domain\User\User;
use App\Loan\Domain\LoanRequest;
use App\Identity\Domain\User\UserRepository;
use App\Loan\Domain\LoanRequestRepository;
use App\Library\Application\Cover\ChangeCoverCommand;
use App\Library\Application\Cover\ChangeCoverHandler;
use App\Library\Domain\Author\Author;
use App\Library\Domain\Author\AuthorRepository;
use App\Library\Domain\Book\Book;
use App\Library\Domain\Book\BookRepository;
use App\Library\Domain\BookCopy\BookCopy;
use App\Library\Domain\BookCopy\BookCopyRepository;
use App\Library\Domain\BookCopy\OwnerId;
use App\Library\Domain\Category\Category;
use App\Library\Domain\Category\CategoryRepository;
use App\Shared\Application\Clock;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class DemoDatasetSeeder
{
    public const string PRIMARY_EMAIL = 'demo@bibliovicino.test';
    public const string ADMIN_EMAIL = 'admin@bibliovicino.test';
    public const string PASSWORD = 'BiblioVicinoDemo!';

    private const array USERS = [
        'demo' => ['email' => self::PRIMARY_EMAIL, 'displayName' => 'Demo BiblioVicino', 'location' => [41.9010, 12.5010]],
        'anna' => ['email' => 'anna@bibliovicino.test', 'displayName' => 'Anna', 'location' => [41.9005, 12.5005]],
        'luca' => ['email' => 'luca@bibliovicino.test', 'displayName' => 'Luca', 'location' => [41.9020, 12.5020]],
        'giulia' => ['email' => 'giulia@bibliovicino.test', 'displayName' => 'Giulia', 'location' => [41.9090, 12.5100]],
        'marco' => ['email' => 'marco@bibliovicino.test', 'displayName' => 'Marco', 'location' => [41.8900, 12.4880]],
        'elena' => ['email' => 'elena@bibliovicino.test', 'displayName' => 'Elena', 'location' => [41.9270, 12.5230]],
        'davide' => ['email' => 'davide@bibliovicino.test', 'displayName' => 'Davide', 'location' => [41.9600, 12.5450]],
        'sara' => ['email' => 'sara@bibliovicino.test', 'displayName' => 'Sara', 'location' => [42.0250, 12.5600]],
        'paolo' => ['email' => 'paolo@bibliovicino.test', 'displayName' => 'Paolo', 'location' => null],
        'admin' => ['email' => self::ADMIN_EMAIL, 'displayName' => 'Amministrazione', 'location' => null],
    ];

    private const array CATEGORY_SLUGS = [
        'narrativa', 'fantascienza', 'fantasy', 'classici',
        'giallo-e-thriller', 'horror', 'saggistica', 'informatica',
    ];

    private const array BOOKS = [
        ['Dune', 1965, ['Frank Herbert'], 'fantascienza'],
        ['Neuromante', 1984, ['William Gibson'], 'fantascienza'],
        ['Fahrenheit 451', 1953, ['Ray Bradbury'], 'fantascienza'],
        ['1984', 1949, ['George Orwell'], 'classici'],
        ['Il nome della rosa', 1980, ['Umberto Eco'], 'giallo-e-thriller'],
        ["Assassinio sull'Orient Express", 1934, ['Agatha Christie'], 'giallo-e-thriller'],
        ['Shining', 1977, ['Stephen King'], 'horror'],
        ['Il Signore degli Anelli', 1954, ['J.R.R. Tolkien'], 'fantasy'],
        ['Lo Hobbit', 1937, ['J.R.R. Tolkien'], 'fantasy'],
        ['American Gods', 2001, ['Neil Gaiman'], 'fantasy'],
        ['Orgoglio e pregiudizio', 1813, ['Jane Austen'], 'classici'],
        ['Il grande Gatsby', 1925, ['F. Scott Fitzgerald'], 'classici'],
        ['Il ritratto di Dorian Gray', 1890, ['Oscar Wilde'], 'classici'],
        ["L'amica geniale", 2011, ['Elena Ferrante'], 'narrativa'],
        ['Sostiene Pereira', 1994, ['Antonio Tabucchi'], 'narrativa'],
        ['La solitudine dei numeri primi', 2008, ['Paolo Giordano'], 'narrativa'],
        ["Se questo è un uomo", 1947, ['Primo Levi'], 'saggistica'],
        ['Sapiens', 2011, ['Yuval Noah Harari'], 'saggistica'],
        ['Breve storia del tempo', 1988, ['Stephen Hawking'], 'saggistica'],
        ['Good Omens', 1990, ['Terry Pratchett', 'Neil Gaiman'], 'fantasy'],
        ['Guida galattica per gli autostoppisti', 1979, ['Douglas Adams'], 'fantascienza'],
        ['Domain-Driven Design: Tackling Complexity in the Heart of Software', 2003, ['Eric Evans'], 'informatica'],
        ['Patterns of Enterprise Application Architecture', 2002, ['Martin Fowler'], 'informatica'],
    ];

    // I nomi delle fixture conservano gli ID Open Library per rendere tracciabile la provenienza.
    private const array COVERS = [
        'Dune' => 15244725,
        'Neuromante' => 14619665,
        'Fahrenheit 451' => 14402436,
        '1984' => 15255646,
        'Il nome della rosa' => 6275469,
        'Assassinio sull\'Orient Express' => 14586276,
        'Shining' => 14656609,
        'Il Signore degli Anelli' => 13119612,
        'Lo Hobbit' => 14625577,
        'American Gods' => 14588932,
        'Orgoglio e pregiudizio' => 14552692,
        'Il grande Gatsby' => 14251213,
        'Il ritratto di Dorian Gray' => 15265884,
        'L\'amica geniale' => 15251367,
        'Sostiene Pereira' => 12704965,
        'La solitudine dei numeri primi' => 5724446,
        'Se questo è un uomo' => 12311586,
        'Sapiens' => 10492831,
        'Breve storia del tempo' => 12174068,
        'Good Omens' => 10482258,
        'Guida galattica per gli autostoppisti' => 14405794,
        'Domain-Driven Design: Tackling Complexity in the Heart of Software' => 7375231,
        'Patterns of Enterprise Application Architecture' => 13414676,
    ];

    private const array COPIES = [
        'demo' => [
            ['Dune'],
            ['Guida galattica per gli autostoppisti'],
            ['Domain-Driven Design: Tackling Complexity in the Heart of Software'],
            ['Patterns of Enterprise Application Architecture'],
            ['Il nome della rosa'],
        ],
        'anna' => [['Dune'], ['1984'], ['Lo Hobbit'], ['Good Omens']],
        'luca' => [
            ['Guida galattica per gli autostoppisti'],
            ['Neuromante'],
            ['Patterns of Enterprise Application Architecture'],
            ['Fahrenheit 451', 'unpublished'],
        ],
        'giulia' => [
            ['Domain-Driven Design: Tackling Complexity in the Heart of Software'],
            ['Il Signore degli Anelli'],
            ["L'amica geniale"],
            ['Orgoglio e pregiudizio', 'unavailable'],
        ],
        'marco' => [['Il nome della rosa'], ['Sapiens'], ['Shining', 'archived']],
        'elena' => [['Il grande Gatsby'], ['Sostiene Pereira'], ['Breve storia del tempo']],
        'davide' => [["Assassinio sull'Orient Express"], ['Il ritratto di Dorian Gray'], ['La solitudine dei numeri primi']],
        'sara' => [["Se questo è un uomo"], ['American Gods']],
        'paolo' => [['Good Omens'], ['Fahrenheit 451']],
    ];

    private const array LOAN_REQUESTS = [
        [-7, 'anna', 'Dune', 'RETURNED'], [-7, 'luca', 'Neuromante', 'RETURNED'],
        [-6, 'anna', '1984', 'REJECTED'],
        [-5, 'anna', 'Lo Hobbit', 'RETURNED'], [-5, 'giulia', 'Il Signore degli Anelli', 'RETURNED'],
        [-4, 'anna', 'Good Omens', 'CANCELLED'],
        [-3, 'luca', 'Guida galattica per gli autostoppisti', 'RETURNED'], [-3, 'marco', 'Il nome della rosa', 'RETURNED'],
        [-2, 'luca', 'Patterns of Enterprise Application Architecture', 'REJECTED'], [-2, 'giulia', "L'amica geniale", 'CANCELLED'],
        [-1, 'giulia', 'Domain-Driven Design: Tackling Complexity in the Heart of Software', 'REJECTED'], [-1, 'marco', 'Sapiens', 'CANCELLED'],
    ];

    public function __construct(
        private Connection $connection,
        private UserRepository $users,
        private PasswordHasher $passwordHasher,
        private SetDiscoveryLocationHandler $setDiscoveryLocation,
        private AuthorRepository $authors,
        private CategoryRepository $categories,
        private BookRepository $books,
        private BookCopyRepository $bookCopies,
        private LoanRequestRepository $loanRequests,
        private Clock $clock,
        private EntityManagerInterface $entityManager,
        private ChangeCoverHandler $changeCover,
        #[Autowire('%kernel.project_dir%/fixtures/demo/covers')]
        private string $coverFixturesDirectory,
    ) {
    }

    public function seed(bool $reset = false): bool
    {
        $this->entityManager->clear();

        try {
            return $this->connection->transactional(function () use ($reset): bool {
                if (!$reset && null !== $this->users->findByEmail(self::PRIMARY_EMAIL)) {
                    return false;
                }

                if ($reset) {
                    $this->connection->executeStatement(
                        'TRUNCATE TABLE loan_request, library_book_copy, library_book_category, library_book_author, library_book, library_author, identity_user',
                    );
                } else {
                    $this->assertNoConflicts();
                }

                $currentWeekStart = $this->clock->now()->modify('monday this week')->setTime(0, 0);
                $createdAt = $currentWeekStart->modify('-8 weeks');
                $users = $this->createUsers($createdAt);
                $categories = $this->createCategories();
                $authors = $this->createAuthors($createdAt);
                $books = $this->createBooks($authors, $categories, $createdAt);
                $copies = $this->createCopies($users, $books, $createdAt);
                $this->createLoanRequests($users, $copies, $currentWeekStart);

                $this->connection->executeStatement(
                    'UPDATE identity_user SET roles = CAST(:roles AS JSON) WHERE id = :id',
                    [
                        'roles' => json_encode(['ROLE_USER', 'ROLE_ADMIN'], JSON_THROW_ON_ERROR),
                        'id' => $users['admin']->id()->toString(),
                    ],
                );

                return true;
            });
        } finally {
            $this->entityManager->clear();
        }
    }

    private function assertNoConflicts(): void
    {
        foreach (self::USERS as $key => $data) {
            if ('demo' !== $key && null !== $this->users->findByEmail($data['email'])) {
                throw new RuntimeException(sprintf('Cannot seed the demo dataset: email %s already exists.', $data['email']));
            }
        }

    }

    /** @return array<string, User> */
    private function createUsers(\DateTimeImmutable $createdAt): array
    {
        $users = [];

        foreach (self::USERS as $key => $data) {
            $user = User::register(
                $this->users->nextId(),
                $data['email'],
                $this->passwordHasher->hash(self::PASSWORD),
                $data['displayName'],
                $createdAt,
            );
            $this->users->save($user);
            $users[$key] = $user;

            if (null !== $data['location']) {
                [$latitude, $longitude] = $data['location'];
                $this->setDiscoveryLocation->handle(new SetDiscoveryLocationCommand(
                    $user->id(),
                    $latitude,
                    $longitude,
                ));
            }
        }

        return $users;
    }

    /** @return array<string, Category> */
    private function createCategories(): array
    {
        $categories = [];

        foreach (self::CATEGORY_SLUGS as $slug) {
            $category = $this->categories->findBySlug($slug);
            if (null === $category || !$category->isActive()) {
                throw new RuntimeException(sprintf('Cannot seed the demo dataset: active reference category %s is missing.', $slug));
            }
            $categories[$slug] = $category;
        }

        return $categories;
    }

    /** @return array<string, Author> */
    private function createAuthors(\DateTimeImmutable $createdAt): array
    {
        $authors = [];
        $now = $createdAt;

        foreach (self::BOOKS as [, , $authorNames]) {
            foreach ($authorNames as $authorName) {
                if (isset($authors[$authorName])) {
                    continue;
                }

                $author = $this->authors->findByNameCaseInsensitive($authorName);

                if (null === $author) {
                    $author = Author::create($this->authors->nextId(), $authorName, $now);
                    $this->authors->save($author);
                }

                $authors[$authorName] = $author;
            }
        }

        return $authors;
    }

    /**
     * @param array<string, Author>   $authors
     * @param array<string, Category> $categories
     *
     * @return array<string, Book>
     */
    private function createBooks(array $authors, array $categories, \DateTimeImmutable $createdAt): array
    {
        $books = [];
        $now = $createdAt;

        foreach (self::BOOKS as [$title, $year, $authorNames, $categorySlug]) {
            $book = Book::create(
                $this->books->nextId(),
                $title,
                $year,
                null,
                array_map(static fn (string $name): Author => $authors[$name], $authorNames),
                [$categories[$categorySlug]],
                $now,
            );
            $this->books->save($book);
            $books[$title] = $book;
        }

        return $books;
    }

    /**
     * @param array<string, User> $users
     * @param array<string, Book> $books
     * @return array<string, BookCopy>
     */
    private function createCopies(array $users, array $books, \DateTimeImmutable $createdAt): array
    {
        $now = $createdAt;
        $copies = [];

        foreach (self::COPIES as $ownerKey => $copyDefinitions) {
            $ownerId = OwnerId::fromString($users[$ownerKey]->id()->toString());

            foreach ($copyDefinitions as $copyDefinition) {
                $title = $copyDefinition[0];
                $state = $copyDefinition[1] ?? null;
                $copy = BookCopy::create(
                    $this->bookCopies->nextId(),
                    $books[$title],
                    $ownerId,
                    null,
                    $now,
                );
                $copy->changeLoanOffer(true, $now);

                if ('unpublished' !== $state) {
                    $copy->publish($now);
                }

                if ('unavailable' === $state) {
                    $copy->makeUnavailable($now);
                }

                $this->bookCopies->save($copy);
                $this->changeCover->handle(new ChangeCoverCommand(
                    $copy->id()->toString(),
                    $ownerId->toString(),
                    temporaryPath: $this->coverFixturesDirectory.'/'.self::COVERS[$title].'.jpg',
                ));

                // L'importazione precede l'archiviazione, che impedisce successive modifiche alla copertina.
                if ('archived' === $state) {
                    $copy->archive($now);
                    $this->bookCopies->save($copy);
                }

                $copies[$ownerKey.':'.$title] = $copy;
            }
        }

        return $copies;
    }

    /**
     * @param array<string, User> $users
     * @param array<string, BookCopy> $copies
     */
    private function createLoanRequests(array $users, array $copies, \DateTimeImmutable $currentWeekStart): void
    {
        $requesterId = $users['demo']->id()->toString();

        foreach (self::LOAN_REQUESTS as [$weekOffset, $ownerKey, $title, $status]) {
            $copy = $copies[$ownerKey.':'.$title];
            $requestedAt = $currentWeekStart->modify(sprintf('%+d weeks', $weekOffset))->modify('+1 day')->setTime(12, 0);
            $request = LoanRequest::request(
                $this->loanRequests->nextId(),
                $copy->id()->toString(),
                $requesterId,
                $requestedAt,
            );

            if ('RETURNED' === $status) {
                $acceptedAt = $requestedAt->modify('+2 hours');
                $lentAt = $requestedAt->modify('+1 day');
                $returnedAt = $requestedAt->modify('+2 days');
                $request->accept($acceptedAt);
                $copy->makeUnavailable($lentAt);
                $request->lend($lentAt);
                $copy->makeAvailable($returnedAt);
                $request->return($returnedAt);
                $this->bookCopies->save($copy);
            } elseif ('REJECTED' === $status) {
                $request->reject($requestedAt->modify('+1 day'));
            } else {
                $request->cancel($requestedAt->modify('+1 day'));
            }

            $this->loanRequests->save($request);
        }
    }
}
