<?php

declare(strict_types=1);

namespace App\Library\Application\ExternalCatalog;

final readonly class ExternalCatalogPage
{
    /** @param list<ExternalWorkSearchResult> $results */
    public function __construct(public array $results, public bool $hasMore)
    {
    }
}
