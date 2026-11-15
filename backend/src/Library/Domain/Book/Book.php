<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use App\Library\Domain\Author\Author;
use App\Library\Domain\Category\Category;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use InvalidArgumentException;

final class Book
{
    /** @var Collection<int, BookAuthor> */
    private Collection $authors;

    /** @var Collection<int, Category> */
    private Collection $categories;

    /**
     * @param array<array-key, Author> $authors
     * @param list<Category>           $categories
     */
    private function __construct(
        private BookId $id,
        private string $title,
        private ?int $publicationYear,
        private ?Isbn $isbn,
        array $authors,
        array $categories,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
        $this->title = self::normaliseTitle($title);
        self::assertPublicationYear($publicationYear);
        $this->authors = new ArrayCollection();
        $this->categories = new ArrayCollection();
        $this->setAuthors($authors);
        $this->setCategories($categories);
    }

    /**
     * @param array<array-key, Author> $authors
     * @param list<Category>           $categories
     */
    public static function create(
        BookId $id,
        string $title,
        ?int $publicationYear,
        ?Isbn $isbn,
        array $authors,
        array $categories,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            $id,
            $title,
            $publicationYear,
            $isbn,
            $authors,
            $categories,
            $createdAt,
            $createdAt,
        );
    }

    public function id(): BookId
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function publicationYear(): ?int
    {
        return $this->publicationYear;
    }

    public function isbn(): ?Isbn
    {
        return $this->isbn;
    }

    /** @return list<BookAuthor> */
    public function authors(): array
    {
        $authors = array_values($this->authors->toArray());
        usort($authors, static fn (BookAuthor $left, BookAuthor $right): int => $left->position() <=> $right->position());

        return $authors;
    }

    /** @return list<Category> */
    public function categories(): array
    {
        return array_values($this->categories->toArray());
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private static function normaliseTitle(string $title): string
    {
        $title = trim($title);
        $length = mb_strlen($title);

        if ($length < 1 || $length > 255) {
            throw new InvalidArgumentException('A Book title must contain between 1 and 255 characters.');
        }

        return $title;
    }

    private static function assertPublicationYear(?int $publicationYear): void
    {
        if (null !== $publicationYear && $publicationYear < 1000) {
            throw new InvalidArgumentException('A publication year must be at least 1000.');
        }
    }

    /** @param array<array-key, Author> $authors */
    private function setAuthors(array $authors): void
    {
        $authorCount = count($authors);

        if ($authorCount < 1 || $authorCount > 10) {
            throw new InvalidArgumentException('A Book must have between 1 and 10 authors.');
        }

        $authorIds = [];

        foreach (array_values($authors) as $position => $author) {
            $authorId = $author->id()->toString();

            if (isset($authorIds[$authorId])) {
                throw new InvalidArgumentException('A Book cannot contain the same Author more than once.');
            }

            $authorIds[$authorId] = true;
            $this->authors->add(BookAuthor::associate($this, $author, $position));
        }
    }

    /** @param list<Category> $categories */
    private function setCategories(array $categories): void
    {
        if ([] === $categories) {
            throw new InvalidArgumentException('A Book must have at least one Category.');
        }

        $categoryIds = [];

        foreach ($categories as $category) {
            if (!$category->isActive()) {
                throw new InvalidArgumentException('An inactive Category cannot be assigned to a Book.');
            }

            $categoryId = $category->id()->toString();

            if (isset($categoryIds[$categoryId])) {
                throw new InvalidArgumentException('A Book cannot contain the same Category more than once.');
            }

            $categoryIds[$categoryId] = true;
            $this->categories->add($category);
        }
    }
}
