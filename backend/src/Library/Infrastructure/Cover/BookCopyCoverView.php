<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Cover;

use App\Library\Application\PublicApi\CoverView;

final readonly class BookCopyCoverView implements CoverView
{
    public function __construct(
        private string $coversBaseUrl,
        private OpenLibraryCoverReferences $references,
    ) {
    }

    public function present(string $copyId, string $source, ?string $reference, bool $owner = false): ?array
    {
        $coverId = 'external' === $source && null !== $reference ? $this->references->coverId($reference) : null;
        if (null !== $coverId) {
            $base = rtrim($this->coversBaseUrl, '/').'/b/id/'.$coverId;
            $view = ['source' => 'external', 'previewUrl' => $base.'-M.jpg?default=false', 'largeUrl' => $base.'-L.jpg?default=false'];
            if ($owner) {
                $view['reference'] = $reference;
            }
            return $view;
        }
        if ('uploaded' === $source && null !== $reference) {
            $base = '/api/library/book-copies/'.rawurlencode($copyId).'/cover/';
            $revision = '?v='.substr(hash('sha256', $reference), 0, 16);
            return ['source' => 'uploaded', 'previewUrl' => $base.'thumbnail'.$revision, 'largeUrl' => $base.'large'.$revision];
        }
        return null;
    }
}
