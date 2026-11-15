<?php

declare(strict_types=1);

namespace App\Identity\Application\GetDiscoveryLocation;

use App\Identity\Domain\User\UserId;

final readonly class GetDiscoveryLocationCommand
{
    public function __construct(public UserId $userId)
    {
    }
}
