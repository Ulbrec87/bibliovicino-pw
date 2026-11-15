<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Persistence;

use App\Library\Domain\Book\Book;
use App\Library\Domain\Book\BookId;
use App\Library\Domain\Book\BookRepository;
use App\Library\Domain\Book\Isbn;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineBookRepository implements BookRepository
{
    public function __construct(private ManagerRegistry $registry)
    {
    }

    public function nextId(): BookId
    {
        return BookId::fromString(Uuid::v7()->toRfc4122());
    }

    public function findById(BookId $id): ?Book
    {
        return $this->registry->getManager()->find(Book::class, $id);
    }

    public function findByIsbn(Isbn $isbn): ?Book
    {
        return $this->registry->getManager()->getRepository(Book::class)->findOneBy(['isbn' => $isbn]);
    }

    public function save(Book $book): void
    {
        $this->registry->getManager()->persist($book);
        $this->registry->getManager()->flush();
    }
}
