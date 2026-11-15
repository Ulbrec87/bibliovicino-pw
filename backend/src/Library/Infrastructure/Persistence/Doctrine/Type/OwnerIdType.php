<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Persistence\Doctrine\Type;

use App\Library\Domain\BookCopy\OwnerId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;

final class OwnerIdType extends Type
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

        if (!$value instanceof OwnerId) {
            throw new InvalidArgumentException('The owner_id Doctrine type expects an OwnerId value.');
        }

        return $value->toString();
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?OwnerId
    {
        if (null === $value || $value instanceof OwnerId) {
            return $value;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException('The owner_id database value must be a UUID string.');
        }

        return OwnerId::fromString($value);
    }
}
