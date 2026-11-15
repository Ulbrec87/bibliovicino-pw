<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Persistence\Doctrine\Type;

use App\Library\Domain\Book\BookId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;

final class BookIdType extends Type
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

        if (!$value instanceof BookId) {
            throw new InvalidArgumentException('The book_id Doctrine type expects a BookId value.');
        }

        return $value->toString();
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?BookId
    {
        if (null === $value || $value instanceof BookId) {
            return $value;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException('The book_id database value must be a UUID string.');
        }

        return BookId::fromString($value);
    }
}
