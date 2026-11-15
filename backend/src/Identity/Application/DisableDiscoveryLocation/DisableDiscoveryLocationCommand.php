<?php

declare(strict_types=1);

namespace App\Identity\Application\DisableDiscoveryLocation;

use App\Identity\Domain\User\UserId;

final readonly class DisableDiscoveryLocationCommand
{
    public function __construct(public UserId $userId)
    {
    }
}
