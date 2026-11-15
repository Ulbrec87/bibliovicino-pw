<?php

declare(strict_types=1);

namespace App\Loan\Domain;

enum LoanRequestStatus: string
{
    case PENDING = 'PENDING';
    case ACCEPTED = 'ACCEPTED';
    case LENT = 'LENT';
    case RETURNED = 'RETURNED';
    case REJECTED = 'REJECTED';
    case CANCELLED = 'CANCELLED';
}
