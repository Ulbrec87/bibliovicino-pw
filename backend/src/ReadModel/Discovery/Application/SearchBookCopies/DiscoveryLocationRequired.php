<?php

declare(strict_types=1);

namespace App\ReadModel\Discovery\Application\SearchBookCopies;

use RuntimeException;

final class DiscoveryLocationRequired extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('A DiscoveryLocation is required to search nearby books.');
    }
}
