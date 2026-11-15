<?php

declare(strict_types=1);

namespace App\Library\Application\SearchExternalBooks;

final readonly class SearchExternalBooksCommand
{
    public function __construct(public string $query, public int $page)
    {
    }
}
