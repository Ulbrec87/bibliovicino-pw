<?php

declare(strict_types=1);

namespace App\Identity\Application\RegisterUser;

use RuntimeException;

final class DuplicateEmail extends RuntimeException
{
}
