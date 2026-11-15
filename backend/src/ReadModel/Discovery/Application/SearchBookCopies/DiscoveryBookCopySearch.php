<?php

declare(strict_types=1);

namespace App\ReadModel\Discovery\Application\SearchBookCopies;

interface DiscoveryBookCopySearch
{
    /** @return null|list<DiscoveryBookCopyRecord> */
    // Null distingue l'assenza della posizione di ricerca da un risultato vuoto valido.
    public function search(DiscoverySearchCriteria $criteria): ?array;
}
