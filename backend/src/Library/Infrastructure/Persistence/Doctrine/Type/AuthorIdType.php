<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Persistence\Doctrine\Type;

use App\Library\Domain\Author\AuthorId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;

final class AuthorIdType extends Type
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

        if (!$value instanceof AuthorId) {
            throw new InvalidArgumentException('The author_id Doctrine type expects an AuthorId value.');
        }

        return $value->toString();
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?AuthorId
    {
        if (null === $value || $value instanceof AuthorId) {
            return $value;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException('The author_id database value must be a UUID string.');
        }

        return AuthorId::fromString($value);
    }
}
