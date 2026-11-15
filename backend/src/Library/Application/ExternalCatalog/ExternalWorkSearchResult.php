<?php

declare(strict_types=1);

namespace App\Library\Application\ExternalCatalog;

final readonly class ExternalWorkSearchResult
{
    public function __construct(
        public string $reference,
        public ExternalEditionSearchResult $edition,
    ) {
    }
}
