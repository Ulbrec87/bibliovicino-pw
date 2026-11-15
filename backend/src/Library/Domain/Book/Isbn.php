<?php

declare(strict_types=1);

namespace App\Library\Domain\Book;

use InvalidArgumentException;
use Stringable;

final readonly class Isbn implements Stringable
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $value = str_replace([' ', '-'], '', trim($value));

        if (10 === strlen($value)) {
            return new self(self::isbn10ToIsbn13($value));
        }

        if (13 === strlen($value) && self::isValidIsbn13($value)) {
            return new self($value);
        }

        throw new InvalidArgumentException('An ISBN must be a valid ISBN-10 or ISBN-13.');
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function isbn10ToIsbn13(string $value): string
    {
        if (1 !== preg_match('/^\d{9}[\dXx]$/D', $value) || !self::isValidIsbn10($value)) {
            throw new InvalidArgumentException('An ISBN must be a valid ISBN-10 or ISBN-13.');
        }

        $base = '978'.substr($value, 0, 9);

        return $base.self::isbn13CheckDigit($base);
    }

    private static function isValidIsbn10(string $value): bool
    {
        $sum = 0;

        for ($position = 0; $position < 10; ++$position) {
            $character = $value[$position];
            $digit = 9 === $position && ('X' === $character || 'x' === $character)
                ? 10
                : (int) $character;
            $sum += (10 - $position) * $digit;
        }

        return 0 === $sum % 11;
    }

    private static function isValidIsbn13(string $value): bool
    {
        return 1 === preg_match('/^(?:978|979)\d{10}$/D', $value)
            && (int) $value[12] === self::isbn13CheckDigit(substr($value, 0, 12));
    }

    private static function isbn13CheckDigit(string $firstTwelveDigits): int
    {
        $sum = 0;

        for ($position = 0; $position < 12; ++$position) {
            $sum += (int) $firstTwelveDigits[$position] * (0 === $position % 2 ? 1 : 3);
        }

        return (10 - ($sum % 10)) % 10;
    }
}
