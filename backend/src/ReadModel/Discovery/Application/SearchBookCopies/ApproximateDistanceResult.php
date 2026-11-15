<?php

declare(strict_types=1);

namespace App\ReadModel\Discovery\Application\SearchBookCopies;

use InvalidArgumentException;

final readonly class ApproximateDistanceResult
{
    private const string LESS_THAN_KM = 'less_than_km';
    private const string AROUND_KM = 'around_km';

    private function __construct(
        public string $kind,
        public int $kilometres,
    ) {
    }

    public static function fromMetres(float $distanceMetres): self
    {
        if (!is_finite($distanceMetres) || $distanceMetres < 0.0) {
            throw new InvalidArgumentException('Distance must be a finite non-negative value.');
        }

        if ($distanceMetres < 1000.0) {
            return new self(self::LESS_THAN_KM, 1);
        }

        return new self(
            self::AROUND_KM,
            (int) round($distanceMetres / 1000.0, 0, PHP_ROUND_HALF_UP),
        );
    }
}
