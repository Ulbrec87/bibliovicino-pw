<?php

declare(strict_types=1);

namespace App\Shared\Application;

use DateTimeImmutable;

interface Clock
{
    // Restituisce l'istante corrente normalizzato in UTC.
    public function now(): DateTimeImmutable;
}
