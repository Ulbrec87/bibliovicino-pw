<?php

declare(strict_types=1);

namespace App\Library\Application\Cover;

use App\Library\Domain\BookCopy\BookCopyCover;
use InvalidArgumentException;

final readonly class CoverChoice
{
    public function __construct(private ExternalCoverReferences $references)
    {
    }

    public function parse(mixed $input): BookCopyCover
    {
        if (null === $input || ['source' => 'none'] === $input) {
            return BookCopyCover::none();
        }
        if (!is_array($input) || count($input) !== 2 || ($input['source'] ?? null) !== 'external' || !is_string($input['reference'] ?? null)) {
            throw new InvalidArgumentException('Choose none or a validated external cover.');
        }
        return $this->references->resolve($input['reference']);
    }
}
