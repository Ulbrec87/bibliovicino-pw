<?php

declare(strict_types=1);

namespace App\Library\Application\SearchExternalBooks;

use App\Library\Application\ExternalCatalog\ExternalEditionSearchResult;
use App\Library\Application\ExternalCatalog\ExternalWorkSearchResult;

final readonly class SearchExternalBooksResult
{
    /**
     * @param 'text'|'isbn' $mode
     * @param 'available'|'empty'|'local'|'unavailable' $status
     * @param list<ExternalWorkSearchResult> $works
     * @param array<string, mixed>|null $localBook
     */
    public function __construct(
        public string $mode,
        public string $status = 'empty',
        public array $works = [],
        public ?ExternalEditionSearchResult $edition = null,
        public ?array $localBook = null,
        public bool $hasMore = false,
    ) {
    }
}
