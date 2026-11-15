<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Infrastructure\Http\CurrentUserResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

final class JsonLoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): JsonResponse
    {
        $user = $token->getUser();

        assert($user instanceof SecurityUser);

        return new JsonResponse(CurrentUserResponse::from($user));
    }
}
