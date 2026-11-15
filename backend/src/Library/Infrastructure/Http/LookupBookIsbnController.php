<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Http;

use App\Library\Application\LookupBookIsbn\LookupBookIsbnCommand;
use App\Library\Application\LookupBookIsbn\LookupBookIsbnHandler;
use App\Shared\Infrastructure\Http\ApiResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class LookupBookIsbnController
{
    public function __construct(private LookupBookIsbnHandler $handler) {}

    #[Route('/api/library/books/isbn/{isbn}', name: 'api_library_lookupbookisbn', methods: ['GET'])]
    public function __invoke(string $isbn): JsonResponse
    {
        try {
            return ApiResponse::success($this->handler->handle(new LookupBookIsbnCommand($isbn)), headers: ['Cache-Control' => 'private, no-store']);
        } catch (InvalidArgumentException) {
            return ApiResponse::validationError(['q' => ['Controlla i dati della ricerca.']]);
        }
    }
}
