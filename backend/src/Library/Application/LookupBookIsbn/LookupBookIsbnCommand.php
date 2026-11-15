<?php

declare(strict_types=1);

namespace App\Library\Application\LookupBookIsbn;

final readonly class LookupBookIsbnCommand
{
    public function __construct(public string $isbn)
    {
    }
}
