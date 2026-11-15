<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Http;

use App\Library\Application\LibraryReadStore;
use App\Shared\Infrastructure\Http\ApiResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ListCategoriesController
{
    public function __construct(private LibraryReadStore $store)
    {
    }

    #[Route('/api/library/categories', name: 'api_library_categories', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success(['categories' => $this->store->activeCategories()], headers: ['Cache-Control' => 'private, no-store']);
    }
}
