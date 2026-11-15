<?php

declare(strict_types=1);

namespace App\Library\Application\Cover;

use App\Library\Application\BookCopyManagement\OwnedBookCopyService;
use App\Library\Domain\BookCopy\BookCopyCover;
use App\Library\Domain\BookCopy\CoverSource;
use App\Shared\Application\Clock;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;

final readonly class ChangeCoverHandler
{
    public function __construct(
        private OwnedBookCopyService $ownedCopy,
        private CoverCopyAccess $copies,
        private CoverImageProcessor $processor,
        private CoverStorage $storage,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function handle(ChangeCoverCommand $command): void
    {
        $copy = $this->ownedCopy->get($command->copyId, $command->ownerId);
        if (null !== $copy->archivedAt()) {
            throw new \DomainException('Archived copy.');
        }
        $copyId = $copy->id()->toString();
        $images = null !== $command->temporaryPath ? $this->processor->process($command->temporaryPath) : null;
        $cover = null !== $images ? BookCopyCover::uploaded(Uuid::v7()->toRfc4122()) : ($command->cover ?? BookCopyCover::none());
        $old = $copy->cover();
        try {
            if (null !== $images) {
                $this->storage->write($copyId, $cover->reference, $images);
            }
            $copy->changeCover($cover, $this->clock->now());
            $this->copies->save($copy);
        } catch (Throwable $exception) {
            if (null !== $images) {
                $this->cleanup($copyId, $cover->reference);
            }
            throw $exception;
        }
        if (CoverSource::UPLOADED === $old->source && $old->reference !== $cover->reference) {
            $this->cleanup($copyId, $old->reference);
        }
    }

    private function cleanup(string $copyId, string $reference): void
    {
        try {
            if (!$this->copies->isReferenced($copyId, $reference)) {
                $this->storage->delete($copyId, $reference);
            }
        } catch (Throwable) {
            $this->logger->warning('Cover cleanup deferred.', ['bookCopyId' => $copyId]);
        }
    }
}
