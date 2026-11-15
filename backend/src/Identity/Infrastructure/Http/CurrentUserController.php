<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http;

use App\Identity\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class CurrentUserController
{
    public function __construct(private Security $security)
    {
    }

    #[Route('/api/auth/me', name: 'api_auth_me', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $user = $this->security->getUser();

        assert($user instanceof SecurityUser);

        return new JsonResponse(CurrentUserResponse::from($user));
    }
}
