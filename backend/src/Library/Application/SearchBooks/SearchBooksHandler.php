<?php

declare(strict_types=1);

namespace App\Library\Application\SearchBooks;

use App\Library\Application\LibraryReadStore;

final readonly class SearchBooksHandler
{
    public function __construct(private LibraryReadStore $store)
    {
    }

    public function handle(SearchBooksCommand $command): array
    {
        $text = new BookSearchText($command->query, $command->page);
        $result = '' === $text->query ? ['results' => [], 'hasMore' => false]
            : $this->store->searchBooks($text->query, $text->canonicalIsbn, $command->page);
        return $result + ['mode' => null === $text->canonicalIsbn ? 'text' : 'isbn', 'canonicalIsbn' => $text->canonicalIsbn];
    }
}
