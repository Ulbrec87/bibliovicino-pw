<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Persistence\Doctrine\Type;

use App\Library\Domain\Category\CategoryId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;

final class CategoryIdType extends Type
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

        if (!$value instanceof CategoryId) {
            throw new InvalidArgumentException('The category_id Doctrine type expects a CategoryId value.');
        }

        return $value->toString();
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?CategoryId
    {
        if (null === $value || $value instanceof CategoryId) {
            return $value;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException('The category_id database value must be a UUID string.');
        }

        return CategoryId::fromString($value);
    }
}
