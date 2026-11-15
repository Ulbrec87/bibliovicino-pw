<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Http;

use App\Library\Application\SearchBooks\SearchBooksCommand;
use App\Library\Application\SearchBooks\SearchBooksHandler;
use App\Shared\Infrastructure\Http\ApiResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class SearchBooksController
{
    public function __construct(private SearchBooksHandler $search)
    {
    }

    #[Route('/api/library/books', name: 'api_library_books_search', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $query = $request->query->all();
        $page = filter_var($query['page'] ?? 1, FILTER_VALIDATE_INT);
        if (!is_string($query['q'] ?? '') || false === $page) {
            return ApiResponse::validationError(['q' => ['Inserisci una ricerca valida.']]);
        }
        try {
            $result = $this->search->handle(new SearchBooksCommand($query['q'] ?? '', $page));
        } catch (InvalidArgumentException) {
            return ApiResponse::validationError(['q' => ['Controlla la ricerca o il numero di pagina.']]);
        }
        return ApiResponse::success($result, headers: ['Cache-Control' => 'private, no-store']);
    }
}
