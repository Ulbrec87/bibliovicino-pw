<?php

declare(strict_types=1);

namespace App\Library\Application\SearchBooks;

final readonly class SearchBooksCommand
{
    public function __construct(public string $query, public int $page)
    {
    }
}
