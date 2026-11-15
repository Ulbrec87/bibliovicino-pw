<?php

declare(strict_types=1);

namespace App\Library\Application\ExternalCatalog;

interface ExternalBookCatalog
{
    public function search(string $query, int $page): ExternalCatalogPage;
    public function findByIsbn(string $canonicalIsbn): ?ExternalEditionSearchResult;
}
