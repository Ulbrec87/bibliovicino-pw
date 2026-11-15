<?php

declare(strict_types=1);

namespace App\ReadModel\Discovery\Application\SearchBookCopies;

use InvalidArgumentException;

final class InvalidDiscoverySearch extends InvalidArgumentException
{
    /** @param array<string, list<string>> $fields */
    public function __construct(public readonly array $fields)
    {
        parent::__construct('The discovery search criteria are invalid.');
    }
}
