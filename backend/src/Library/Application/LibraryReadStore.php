<?php

declare(strict_types=1);

namespace App\Library\Application;

interface LibraryReadStore
{
    /** @return array{results: list<array<string, mixed>>, hasMore: bool} */
    public function searchBooks(string $query, ?string $canonicalIsbn, int $page): array;

    /** @return list<array<string, mixed>> */
    public function activeCategories(): array;

    /** @return array<string, mixed>|null */
    public function bookByIsbn(string $canonicalIsbn): ?array;
}
