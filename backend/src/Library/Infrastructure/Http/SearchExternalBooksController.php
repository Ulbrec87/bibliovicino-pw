<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Http;

use App\Library\Application\SearchExternalBooks\SearchExternalBooksCommand;
use App\Library\Application\SearchExternalBooks\SearchExternalBooksHandler;
use App\Shared\Infrastructure\Http\ApiResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class SearchExternalBooksController
{
    public function __construct(private SearchExternalBooksHandler $handler)
    {
    }

    #[Route('/api/library/external-books/search', name: 'api_library_searchexternalbooks', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $parameters = $request->query->all();
        $page = filter_var($parameters['page'] ?? 1, FILTER_VALIDATE_INT);
        $query = $parameters['q'] ?? '';
        if (false === $page || !is_string($query)) {
            return ApiResponse::validationError(['q' => ['Inserisci una ricerca valida.']]);
        }

        try {
            $result = $this->handler->handle(new SearchExternalBooksCommand($query, $page));
        } catch (InvalidArgumentException) {
            return ApiResponse::validationError(['q' => ['Controlla i dati della ricerca.']]);
        }

        return ApiResponse::success([
            'mode' => $result->mode,
            'status' => $result->status,
            'works' => $result->works,
            'edition' => $result->edition,
            'localBook' => $result->localBook,
            'hasMore' => $result->hasMore,
        ], headers: ['Cache-Control' => 'private, no-store']);
    }
}
