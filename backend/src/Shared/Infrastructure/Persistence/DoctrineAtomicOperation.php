<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use App\Shared\Application\AtomicOperation;
use App\Shared\Application\UniqueConstraintConflict;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\Persistence\ManagerRegistry;
use Throwable;

final readonly class DoctrineAtomicOperation implements AtomicOperation
{
    public function __construct(private ManagerRegistry $registry) {}

    public function run(callable $operation): mixed
    {
        try {
            return $this->registry->getManager()->wrapInTransaction(static fn () => $operation());
        } catch (Throwable $exception) {
            // Dopo il fallimento sostituiamo l'EntityManager, evitando di riusare quello chiuso dalla transazione.
            $this->registry->resetManager();
            if ($exception instanceof UniqueConstraintViolationException) {
                throw new UniqueConstraintConflict(previous: $exception);
            }
            throw $exception;
        }
    }
}
