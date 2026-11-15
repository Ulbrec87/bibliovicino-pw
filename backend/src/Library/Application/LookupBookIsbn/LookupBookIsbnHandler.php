<?php

declare(strict_types=1);

namespace App\Library\Application\LookupBookIsbn;

use App\Library\Application\LibraryReadStore;
use App\Library\Domain\Book\Isbn;

final readonly class LookupBookIsbnHandler
{
    public function __construct(private LibraryReadStore $store) {}

    public function handle(LookupBookIsbnCommand $command): array
    {
        $isbn = Isbn::fromString($command->isbn)->toString();
        return ['canonicalIsbn' => $isbn, 'book' => $this->store->bookByIsbn($isbn)];
    }
}
