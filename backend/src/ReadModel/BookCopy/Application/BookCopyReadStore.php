<?php

declare(strict_types=1);

namespace App\ReadModel\BookCopy\Application;

interface BookCopyReadStore
{
    public function detail(string $id, string $viewerId): ?BookCopyDetailResult;

    public function publicLibrary(string $ownerId): ?PublicLibraryResult;
}
