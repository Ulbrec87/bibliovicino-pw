<?php

declare(strict_types=1);

namespace App\Library\Domain\BookCopy;

use InvalidArgumentException;

final readonly class BookCopyCover
{
    private function __construct(public CoverSource $source, public ?string $reference)
    {
    }

    public static function none(): self
    {
        return new self(CoverSource::NONE, null);
    }
    public static function external(string $reference): self
    {
        if ('' === trim($reference)) {
            throw new InvalidArgumentException('Cover reference cannot be empty.');
        }

        return new self(CoverSource::EXTERNAL, $reference);
    }
    public static function uploaded(string $reference): self
    {
        if ('' === trim($reference)) {
            throw new InvalidArgumentException('Cover reference cannot be empty.');
        }

        return new self(CoverSource::UPLOADED, $reference);
    }
}
