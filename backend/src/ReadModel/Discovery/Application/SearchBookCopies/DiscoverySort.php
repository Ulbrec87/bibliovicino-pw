<?php

declare(strict_types=1);

namespace App\ReadModel\Discovery\Application\SearchBookCopies;

enum DiscoverySort: string
{
    case RELEVANCE = 'relevance';
    case DISTANCE = 'distance';
}
