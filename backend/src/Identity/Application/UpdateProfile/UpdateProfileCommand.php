<?php

declare(strict_types=1);

namespace App\Identity\Application\UpdateProfile;

use App\Identity\Domain\User\UserId;

final readonly class UpdateProfileCommand
{
    public function __construct(
        public UserId $userId,
        public string $displayName,
    ) {
    }
}
