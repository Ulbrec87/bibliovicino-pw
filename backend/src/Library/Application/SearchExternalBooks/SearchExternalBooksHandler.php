<?php

declare(strict_types=1);

namespace App\Library\Application\SearchExternalBooks;

use App\Library\Application\ExternalCatalog\ExternalBookCatalog;
use App\Library\Application\ExternalCatalog\ExternalCatalogUnavailable;
use App\Library\Application\LibraryReadStore;
use App\Library\Application\SearchBooks\BookSearchText;

final readonly class SearchExternalBooksHandler
{
    public function __construct(private ExternalBookCatalog $catalog, private LibraryReadStore $store)
    {
    }

    public function handle(SearchExternalBooksCommand $command): SearchExternalBooksResult
    {
        $text = new BookSearchText($command->query, $command->page);
        $mode = null === $text->canonicalIsbn ? 'text' : 'isbn';

        if ('' === $text->query) {
            return new SearchExternalBooksResult(mode: $mode);
        }

        if (null !== $text->canonicalIsbn) {
            $local = $this->store->bookByIsbn($text->canonicalIsbn);
            if (null !== $local) {
                return new SearchExternalBooksResult(mode: $mode, status: 'local', localBook: $local);
            }
        }

        try {
            if (null !== $text->canonicalIsbn) {
                $edition = $this->catalog->findByIsbn($text->canonicalIsbn);
                if (null === $edition) {
                    return new SearchExternalBooksResult(mode: $mode);
                }

                return new SearchExternalBooksResult(mode: $mode, status: 'available', edition: $edition);
            }

            $page = $this->catalog->search($text->query, $command->page);

            return new SearchExternalBooksResult(
                mode: $mode,
                status: [] === $page->results ? 'empty' : 'available',
                works: $page->results,
                hasMore: $page->hasMore,
            );
        } catch (ExternalCatalogUnavailable) {
            return new SearchExternalBooksResult(mode: $mode, status: 'unavailable');
        }
    }
}
