<?php

declare(strict_types=1);

namespace App\Identity\Application\PublicApi;

interface CurrentUserId
{
    public function get(): ?string;
}
