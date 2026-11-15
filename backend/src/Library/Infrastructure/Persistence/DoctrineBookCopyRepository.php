<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Persistence;

use App\Library\Domain\BookCopy\BookCopy;
use App\Library\Domain\BookCopy\BookCopyId;
use App\Library\Domain\BookCopy\BookCopyRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineBookCopyRepository implements BookCopyRepository
{
    public function __construct(private ManagerRegistry $registry)
    {
    }

    public function nextId(): BookCopyId
    {
        return BookCopyId::fromString(Uuid::v7()->toRfc4122());
    }

    public function findById(BookCopyId $id): ?BookCopy
    {
        return $this->registry->getManager()->find(BookCopy::class, $id);
    }

    public function save(BookCopy $copy): void
    {
        $this->registry->getManager()->persist($copy);
        $this->registry->getManager()->flush();
    }
}
