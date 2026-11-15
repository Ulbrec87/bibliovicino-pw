<?php

declare(strict_types=1);

namespace App\Identity\Domain\User;

use DomainException;

final class InvalidDisplayName extends DomainException
{
    public function __construct()
    {
        parent::__construct('Il nome visualizzato deve contenere da 1 a 80 caratteri.');
    }
}
