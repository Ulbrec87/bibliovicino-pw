<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Cover;

use App\Library\Application\Cover\ExternalCoverReferences;
use App\Library\Domain\BookCopy\BookCopyCover;
use InvalidArgumentException;

final readonly class OpenLibraryCoverReferences implements ExternalCoverReferences
{
    private const string PREFIX = 'open-library:';

    public function issue(int $coverId): string
    {
        if ($coverId < 1) {
            throw new InvalidArgumentException('Invalid cover ID.');
        }
        return self::PREFIX.$coverId;
    }

    public function resolve(string $reference): BookCopyCover
    {
        if (null === $this->coverId($reference)) {
            throw new InvalidArgumentException('Invalid external cover reference.');
        }
        return BookCopyCover::external($reference);
    }

    public function coverId(string $reference): ?int
    {
        $id = filter_var(substr($reference, strlen(self::PREFIX)), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return false !== $id && $reference === self::PREFIX.$id ? $id : null;
    }
}
