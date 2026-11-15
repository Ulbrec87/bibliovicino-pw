<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http;

use App\Identity\Application\RegisterUser\DuplicateEmail;
use App\Identity\Application\RegisterUser\RegisterUserCommand;
use App\Identity\Application\RegisterUser\RegisterUserHandler;
use App\Identity\Domain\User\InvalidDisplayName;
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

final readonly class RegisterController
{
    public function __construct(
        private RegisterUserHandler $registerUser,
        private ValidatorInterface $validator,
        private Security $security,
    ) {
    }

    #[Route('/api/auth/register', name: 'api_auth_register', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
        } catch (JsonException) {
            return ApiResponse::error('invalid_json', 'The request body must be valid JSON.', Response::HTTP_BAD_REQUEST);
        }

        $input = new RegistrationInput(
            $payload['email'] ?? null,
            $payload['password'] ?? null,
            $payload['displayName'] ?? null,
        );
        $violations = $this->validator->validate($input);

        if (count($violations) > 0) {
            return ApiResponse::validationError(ValidationErrorsMapper::fromViolations($violations));
        }

        try {
            $user = $this->registerUser->handle(new RegisterUserCommand(
                (string) $input->email,
                (string) $input->password,
                (string) $input->displayName,
            ));
        } catch (InvalidDisplayName $exception) {
            return ApiResponse::validationError(['displayName' => [$exception->getMessage()]]);
        } catch (DuplicateEmail) {
            return ApiResponse::error(
                'email_already_registered',
                'An account with this email already exists.',
                Response::HTTP_CONFLICT,
            );
        }

        $securityUser = SecurityUser::fromDomain($user);
        $this->security->login($securityUser, 'json_login', 'main');

        return ApiResponse::success(CurrentUserResponse::from($securityUser), Response::HTTP_CREATED);
    }
}
