<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegistrationInput
{
    public function __construct(
        #[Assert\Sequentially([
            new Assert\NotBlank(),
            new Assert\Type('string'),
            new Assert\Email(),
        ])]
        public mixed $email,
        #[Assert\Sequentially([
            new Assert\NotBlank(),
            new Assert\Type('string'),
            new Assert\Length(min: 12, max: 64),
        ])]
        public mixed $password,
        #[Assert\Sequentially([
            new Assert\NotNull(),
            new Assert\Type('string'),
        ])]
        public mixed $displayName,
    ) {
    }
}
