<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Cover;

use App\Library\Application\Cover\CoverCopyAccess;
use App\Library\Domain\BookCopy\BookCopy;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;
use Throwable;

final readonly class DoctrineCoverCopyAccess implements CoverCopyAccess
{
    public function __construct(private ManagerRegistry $registry)
    {
    }

    public function save(BookCopy $copy): void
    {
        try {
            $this->registry->getManager()->flush();
        } catch (Throwable $exception) {
            $this->registry->resetManager();
            throw $exception;
        }
    }

    public function visible(string $copyId, string $viewerId): ?array
    {
        if (!Uuid::isValid($copyId)) {
            return null;
        }
        $row = $this->registry->getConnection()->fetchAssociative(<<<'SQL'
            SELECT cover_source AS source, cover_reference AS reference FROM library_book_copy
            WHERE id = :id AND archived_at IS NULL AND (owner_id = :viewer OR is_published = TRUE)
            SQL, ['id' => $copyId, 'viewer' => $viewerId]);
        return false === $row ? null : $row;
    }

    public function isReferenced(string $copyId, string $reference): bool
    {
        return false !== $this->registry->getConnection()->fetchOne("SELECT 1 FROM library_book_copy WHERE id = :id AND cover_source = 'uploaded' AND cover_reference = :reference", ['id' => $copyId, 'reference' => $reference]);
    }
}
