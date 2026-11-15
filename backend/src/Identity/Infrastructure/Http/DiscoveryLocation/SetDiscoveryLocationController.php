<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http\DiscoveryLocation;

use App\Identity\Application\SetDiscoveryLocation\InvalidDiscoveryCoordinates;
use App\Identity\Application\SetDiscoveryLocation\SetDiscoveryLocationCommand;
use App\Identity\Application\SetDiscoveryLocation\SetDiscoveryLocationHandler;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Infrastructure\Http\ApiResponse;
use App\Shared\Infrastructure\Http\ValidationErrorsMapper;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class SetDiscoveryLocationController
{
    private const array RESPONSE_HEADERS = ['Cache-Control' => 'no-store'];

    public function __construct(
        private SetDiscoveryLocationHandler $setDiscoveryLocation,
        private ValidatorInterface $validator,
        private Security $security,
    ) {
    }

    #[Route('/api/profile/discovery-location', name: 'api_profile_discovery_location_set', methods: ['PUT'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (JsonException) {
            return ApiResponse::error(
                'invalid_json',
                'The request body must be valid JSON.',
                Response::HTTP_BAD_REQUEST,
                self::RESPONSE_HEADERS,
            );
        }

        $input = new SetDiscoveryLocationInput(
            $payload['latitude'] ?? null,
            $payload['longitude'] ?? null,
        );
        $violations = $this->validator->validate($input);

        if (count($violations) > 0) {
            return ApiResponse::validationError(
                ValidationErrorsMapper::fromViolations($violations),
                self::RESPONSE_HEADERS,
            );
        }

        $securityUser = $this->security->getUser();

        assert($securityUser instanceof SecurityUser);

        try {
            $result = $this->setDiscoveryLocation->handle(new SetDiscoveryLocationCommand(
                $securityUser->id(),
                (float) $input->latitude,
                (float) $input->longitude,
            ));
        } catch (InvalidDiscoveryCoordinates) {
            return ApiResponse::error(
                'validation_failed',
                'The submitted data is invalid.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                self::RESPONSE_HEADERS,
            );
        }

        return ApiResponse::success(
            DiscoveryLocationResponse::fromResult($result),
            headers: self::RESPONSE_HEADERS,
        );
    }
}
