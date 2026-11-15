<?php

declare(strict_types=1);

namespace App\ReadModel\Discovery\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SearchBookCopiesInput
{
    public function __construct(
        #[Assert\Sequentially([
            new Assert\Type('string'),
            new Assert\Length(max: 255, normalizer: 'trim'),
        ])]
        public mixed $q,
        #[Assert\Sequentially([
            new Assert\Type('string'),
            new Assert\Choice(choices: ['1', '2', '5', '10']),
        ])]
        public mixed $radiusKm,
        #[Assert\Sequentially([
            new Assert\Type('string'),
            new Assert\Choice(choices: ['relevance', 'distance']),
        ])]
        public mixed $sort,
    ) {
    }
}
