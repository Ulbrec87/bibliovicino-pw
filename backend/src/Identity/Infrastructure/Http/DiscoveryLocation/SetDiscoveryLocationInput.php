<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http\DiscoveryLocation;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SetDiscoveryLocationInput
{
    public function __construct(
        #[Assert\Sequentially([
            new Assert\NotNull(),
            new Assert\Type(['integer', 'float']),
            new Assert\Range(min: -90, max: 90),
        ])]
        public mixed $latitude,
        #[Assert\Sequentially([
            new Assert\NotNull(),
            new Assert\Type(['integer', 'float']),
            new Assert\Range(min: -180, max: 180),
        ])]
        public mixed $longitude,
    ) {
    }
}
