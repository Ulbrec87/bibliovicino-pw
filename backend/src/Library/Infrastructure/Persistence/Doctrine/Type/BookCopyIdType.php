<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Persistence\Doctrine\Type;

use App\Library\Domain\BookCopy\BookCopyId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;

final class BookCopyIdType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGuidTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof BookCopyId) {
            throw new InvalidArgumentException('The book_copy_id Doctrine type expects a BookCopyId value.');
        }

        return $value->toString();
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?BookCopyId
    {
        if (null === $value || $value instanceof BookCopyId) {
            return $value;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException('The book_copy_id database value must be a UUID string.');
        }

        return BookCopyId::fromString($value);
    }
}
