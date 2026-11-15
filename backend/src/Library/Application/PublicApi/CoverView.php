<?php

declare(strict_types=1);

namespace App\Library\Application\PublicApi;

interface CoverView
{
    /** @return array{source: string, previewUrl: string, largeUrl: string, reference?: string}|null */
    public function present(string $copyId, string $source, ?string $reference, bool $owner = false): ?array;
}
