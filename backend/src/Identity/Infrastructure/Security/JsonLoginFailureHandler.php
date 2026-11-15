<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

final class JsonLoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return new JsonResponse([
                'error' => [
                    'code' => 'too_many_login_attempts',
                    'message' => 'Too many login attempts. Try again later.',
                ],
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        return new JsonResponse([
            'error' => [
                'code' => 'invalid_credentials',
                'message' => 'Invalid credentials.',
            ],
        ], Response::HTTP_UNAUTHORIZED);
    }
}
