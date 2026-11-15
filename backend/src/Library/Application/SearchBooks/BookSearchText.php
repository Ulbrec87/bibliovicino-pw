<?php

declare(strict_types=1);

namespace App\Library\Application\SearchBooks;

use App\Library\Domain\Book\Isbn;
use InvalidArgumentException;

final readonly class BookSearchText
{
    public string $query;
    public ?string $canonicalIsbn;

    public function __construct(string $query, int $page)
    {
        $this->query = trim($query);
        if (mb_strlen($this->query) > 255 || $page < 1 || $page > 1000) {
            throw new InvalidArgumentException('Invalid book search.');
        }
        $isbn = null;
        $compact = str_replace([' ', '-'], '', $this->query);
        if (preg_match('/^(?:[0-9]{9}[0-9Xx]|[0-9]{13})$/D', $compact)) {
            try {
                $isbn = Isbn::fromString($this->query)->toString();
            } catch (InvalidArgumentException) {
            }
        }
        $this->canonicalIsbn = $isbn;
    }
}
