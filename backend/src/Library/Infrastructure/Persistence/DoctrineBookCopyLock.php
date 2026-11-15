<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Persistence;

use App\Library\Application\BookCopyManagement\BookCopyLock;
use App\Library\Domain\BookCopy\BookCopy;
use App\Library\Domain\BookCopy\BookCopyId;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;

final readonly class DoctrineBookCopyLock implements BookCopyLock
{
    public function __construct(private ManagerRegistry $registry) {}

    public function lock(string $id): ?BookCopy
    {
        try {
            $copyId = BookCopyId::fromString($id);
        } catch (InvalidArgumentException) {
            return null;
        }
        return $this->registry->getManager()->find(BookCopy::class, $copyId, LockMode::PESSIMISTIC_WRITE);
    }
}
