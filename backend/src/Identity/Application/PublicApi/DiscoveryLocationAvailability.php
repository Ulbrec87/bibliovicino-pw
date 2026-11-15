<?php

declare(strict_types=1);

namespace App\Identity\Application\PublicApi;

interface DiscoveryLocationAvailability
{
    public function isConfigured(string $userId): bool;
}
