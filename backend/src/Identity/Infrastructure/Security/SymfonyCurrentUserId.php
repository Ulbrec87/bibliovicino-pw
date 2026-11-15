<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\PublicApi\CurrentUserId;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class SymfonyCurrentUserId implements CurrentUserId
{
    public function __construct(private Security $security)
    {
    }

    public function get(): ?string
    {
        $user = $this->security->getUser();
        return $user instanceof SecurityUser ? $user->id()->toString() : null;
    }
}
