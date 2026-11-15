<?php

declare(strict_types=1);

namespace App\ReadModel\Discovery\Application\SearchBookCopies;

final readonly class ApproximatePointResult
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
    }
}
