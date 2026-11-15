<?php

declare(strict_types=1);

namespace App\Library\Application\Cover;

interface CoverImageProcessor
{
    public function process(string $temporaryPath): ProcessedCover;
}
