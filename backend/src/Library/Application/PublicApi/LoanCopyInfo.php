<?php

declare(strict_types=1);

namespace App\Library\Application\PublicApi;

final readonly class LoanCopyInfo
{
    public function __construct(
        public string $id,
        public string $ownerId,
        public bool $eligible,
        public bool $publiclyVisible,
        public string $availabilityStatus,
    ) {}
}
