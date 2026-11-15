<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Persistence\Doctrine\Type;

use App\Library\Domain\Book\Isbn;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;

final class IsbnType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL(['length' => 13, ...$column]);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof Isbn) {
            throw new InvalidArgumentException('The isbn Doctrine type expects an Isbn value.');
        }

        return $value->toString();
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Isbn
    {
        if (null === $value || $value instanceof Isbn) {
            return $value;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException('The isbn database value must be a string.');
        }

        return Isbn::fromString($value);
    }
}
