<?php

declare(strict_types=1);

namespace App\Library\Application\ExternalCatalog;

final readonly class ExternalEditionSearchResult
{
    /** @param list<string> $authors */
    public function __construct(
        public string $reference,
        public string $title,
        public array $authors,
        public ?int $publicationYear,
        public ?string $isbn,
        public ?string $coverPreviewUrl,
        public bool $correctionRequired,
        public ?string $coverLargeUrl = null,
        public ?string $coverReference = null,
    )
    {
    }
}
