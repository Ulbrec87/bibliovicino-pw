<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\Validator\ConstraintViolationListInterface;

final class ValidationErrorsMapper
{
    /** @return array<string, list<string>> */
    public static function fromViolations(ConstraintViolationListInterface $violations): array
    {
        $errors = [];

        foreach ($violations as $violation) {
            $errors[$violation->getPropertyPath()][] = $violation->getMessage();
        }

        return $errors;
    }

    private function __construct()
    {
    }
}
