<?php

declare(strict_types=1);

namespace App\ReadModel\Discovery\Infrastructure\Http;

use App\Identity\Application\PublicApi\CurrentUserId;
use App\ReadModel\Discovery\Application\SearchBookCopies\DiscoveryLocationRequired;
use App\ReadModel\Discovery\Application\SearchBookCopies\InvalidDiscoverySearch;
use App\ReadModel\Discovery\Application\SearchBookCopies\SearchBookCopiesCommand;
use App\ReadModel\Discovery\Application\SearchBookCopies\SearchBookCopiesHandler;
use App\Shared\Infrastructure\Http\ApiResponse;
use App\Shared\Infrastructure\Http\ValidationErrorsMapper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class SearchBookCopiesController
{
    private const array RESPONSE_HEADERS = ['Cache-Control' => 'private, no-store'];

    public function __construct(
        private SearchBookCopiesHandler $searchBookCopies,
        private ValidatorInterface $validator,
        private CurrentUserId $currentUserId,
    ) {
    }

    #[Route('/api/discovery/book-copies', name: 'api_discovery_book_copies_search', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $queryParameters = $request->query->all();
        $input = new SearchBookCopiesInput(
            $queryParameters['q'] ?? null,
            $queryParameters['radiusKm'] ?? null,
            $queryParameters['sort'] ?? null,
        );
        $violations = $this->validator->validate($input);

        if (count($violations) > 0) {
            return ApiResponse::validationError(
                ValidationErrorsMapper::fromViolations($violations),
                self::RESPONSE_HEADERS,
            );
        }

        $ownerId = $this->currentUserId->get();
        if (null === $ownerId) {
            throw new AccessDeniedException();
        }

        try {
            $result = $this->searchBookCopies->handle(new SearchBookCopiesCommand(
                $ownerId,
                null === $input->q ? null : (string) $input->q,
                null === $input->radiusKm ? null : (int) $input->radiusKm,
                null === $input->sort ? null : (string) $input->sort,
            ));
        } catch (InvalidDiscoverySearch $exception) {
            return ApiResponse::validationError($exception->fields, self::RESPONSE_HEADERS);
        } catch (DiscoveryLocationRequired) {
            return ApiResponse::error(
                'discovery_location_required',
                'Configure a discovery location before searching nearby books.',
                Response::HTTP_CONFLICT,
                self::RESPONSE_HEADERS,
            );
        }

        return ApiResponse::success(
            DiscoverySearchResponse::fromResult($result),
            headers: self::RESPONSE_HEADERS,
        );
    }
}
