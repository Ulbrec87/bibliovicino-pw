<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http;

use App\Identity\Infrastructure\Security\SecurityUser;

final class CurrentUserResponse
{
    /** @return array{user: array{id: string, email: string, displayName: string, roles: list<string>}} */
    public static function from(SecurityUser $user): array
    {
        return [
            'user' => [
                'id' => $user->id()->toString(),
                'email' => $user->getUserIdentifier(),
                'displayName' => $user->displayName(),
                'roles' => $user->getRoles(),
            ],
        ];
    }

    private function __construct()
    {
    }
}
