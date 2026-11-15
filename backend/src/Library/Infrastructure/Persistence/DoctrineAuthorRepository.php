<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Persistence;

use App\Library\Domain\Author\Author;
use App\Library\Domain\Author\AuthorId;
use App\Library\Domain\Author\AuthorRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineAuthorRepository implements AuthorRepository
{
    public function __construct(private ManagerRegistry $registry)
    {
    }

    public function nextId(): AuthorId
    {
        return AuthorId::fromString(Uuid::v7()->toRfc4122());
    }

    public function findById(AuthorId $id): ?Author
    {
        return $this->registry->getManager()->find(Author::class, $id);
    }

    public function findByNameCaseInsensitive(string $name): ?Author
    {
        return $this->registry->getManager()->createQueryBuilder()
            ->select('author')
            ->from(Author::class, 'author')
            ->where('LOWER(author.name) = LOWER(:name)')
            ->setParameter('name', Author::normaliseName($name))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function save(Author $author): void
    {
        $this->registry->getManager()->persist($author);
        $this->registry->getManager()->flush();
    }
}
