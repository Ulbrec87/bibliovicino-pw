<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http;

use App\Identity\Application\UpdateProfile\UpdateProfileCommand;
use App\Identity\Application\UpdateProfile\UpdateProfileHandler;
use App\Identity\Domain\User\InvalidDisplayName;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Shared\Infrastructure\Http\ApiResponse;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class UpdateProfileController
{
    private const array RESPONSE_HEADERS = ['Cache-Control' => 'private, no-store'];

    public function __construct(
        private UpdateProfileHandler $updateProfile,
        private Security $security,
    ) {
    }

    #[Route('/api/profile', name: 'api_profile_update', methods: ['PATCH'])]
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

        if (count($payload) !== 1 || !isset($payload['displayName']) || !is_string($payload['displayName'])) {
            return ApiResponse::validationError(
                ['displayName' => ['Invia soltanto il nome visualizzato, come testo da 1 a 80 caratteri.']],
                self::RESPONSE_HEADERS,
            );
        }

        $securityUser = $this->security->getUser();
        assert($securityUser instanceof SecurityUser);

        try {
            $user = $this->updateProfile->handle(new UpdateProfileCommand(
                $securityUser->id(),
                $payload['displayName'],
            ));
        } catch (InvalidDisplayName $exception) {
            return ApiResponse::validationError(
                ['displayName' => [$exception->getMessage()]],
                self::RESPONSE_HEADERS,
            );
        }

        return ApiResponse::success(
            CurrentUserResponse::from(SecurityUser::fromDomain($user)),
            headers: self::RESPONSE_HEADERS,
        );
    }
}
