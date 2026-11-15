<?php

declare(strict_types=1);

namespace App\Library\Application\CreateBookCopy;

use App\Identity\Application\PublicApi\DiscoveryLocationAvailability;
use App\Library\Application\LibraryReadStore;
use App\Library\Domain\Author\Author;
use App\Library\Domain\Author\AuthorRepository;
use App\Library\Domain\Book\Book;
use App\Library\Domain\Book\BookRepository;
use App\Library\Domain\BookCopy\BookCopy;
use App\Library\Domain\BookCopy\BookCopyRepository;
use App\Library\Domain\BookCopy\OwnerId;
use App\Library\Domain\Category\Category;
use App\Library\Domain\Category\CategoryId;
use App\Library\Domain\Category\CategoryRepository;
use App\Shared\Application\AtomicOperation;
use App\Shared\Application\Clock;
use App\Shared\Application\UniqueConstraintConflict;
use DateTimeImmutable;

final readonly class CreateBookCopyHandler
{
    public function __construct(
        private AuthorRepository $authors,
        private BookRepository $books,
        private BookCopyRepository $copies,
        private CategoryRepository $categories,
        private LibraryReadStore $readStore,
        private AtomicOperation $transaction,
        private DiscoveryLocationAvailability $discoveryLocation,
        private Clock $clock,
    ) {
    }

    public function handle(CreateBookCopyCommand $command): CreateBookCopyResult
    {
        $isbn = $command->newBook?->isbn;
        if (null !== $isbn) {
            $existing = $this->readStore->bookByIsbn($isbn->toString());
            if (null !== $existing) {
                throw new BookAlreadyExists($existing);
            }
        }
        if ($command->publish && !$this->discoveryLocation->isConfigured($command->ownerId)) {
            throw new DiscoveryLocationRequired();
        }

        try {
            return $this->transaction->run(fn (): CreateBookCopyResult => $this->create($command));
        } catch (UniqueConstraintConflict) {
            $existing = null !== $isbn ? $this->readStore->bookByIsbn($isbn->toString()) : null;
            if (null !== $existing) {
                throw new BookAlreadyExists($existing);
            }
            throw new InvalidBookCopyInput(['newBook' => ['Non è stato possibile completare il salvataggio. Riprova.']]);
        }
    }

    private function create(CreateBookCopyCommand $command): CreateBookCopyResult
    {
        $now = $this->clock->now();
        $book = $this->resolveBook($command, $now);
        $copy = BookCopy::create(
            $this->copies->nextId(),
            $book,
            OwnerId::fromString($command->ownerId),
            $command->notes,
            $now,
        );
        if (null !== $command->cover) {
            $copy->changeCover($command->cover, $now);
        }
        if ($command->publish) {
            $copy->changeLoanOffer(true, $now);
            $copy->publish($now);
        }
        $this->copies->save($copy);

        return new CreateBookCopyResult($copy->id()->toString(), $book->id()->toString(), $copy->isPublished());
    }

    private function resolveBook(CreateBookCopyCommand $command, DateTimeImmutable $now): Book
    {
        if (null !== $command->bookId) {
            $book = $this->books->findById($command->bookId);
            if (null === $book) {
                throw new BookUnavailable('Il libro selezionato non è più disponibile.');
            }
            return $book;
        }

        $data = $command->newBook;
        assert(null !== $data);
        $categories = $this->resolveCategories($data->categoryIds);
        $authors = $this->resolveAuthors($data->authors, $now);
        $book = Book::create(
            $this->books->nextId(),
            $data->title,
            $data->publicationYear,
            $data->isbn,
            $authors,
            $categories,
            $now,
        );
        $this->books->save($book);
        return $book;
    }

    /**
     * @param list<string> $names
     * @return list<Author>
     */
    private function resolveAuthors(array $names, DateTimeImmutable $now): array
    {
        $authors = [];
        foreach ($names as $name) {
            $author = $this->authors->findByNameCaseInsensitive($name);
            if (null === $author) {
                $author = Author::create($this->authors->nextId(), $name, $now);
                $this->authors->save($author);
            }
            $authors[] = $author;
        }
        return $authors;
    }

    /**
     * @param list<CategoryId> $ids
     * @return list<Category>
     */
    private function resolveCategories(array $ids): array
    {
        $categories = [];
        foreach ($ids as $id) {
            $category = $this->categories->findById($id);
            if (null === $category || !$category->isActive()) {
                throw new InvalidBookCopyInput(['newBook.categoryIds' => ['Seleziona soltanto categorie attive.']]);
            }
            $categories[] = $category;
        }
        return $categories;
    }
}
