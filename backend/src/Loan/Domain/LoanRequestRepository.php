<?php

declare(strict_types=1);

namespace App\Loan\Domain;

interface LoanRequestRepository
{
    public function nextId(): string;
    public function copyIdOf(string $requestId): ?string;
    public function findById(string $requestId): ?LoanRequest;
    public function hasActiveForCopy(string $copyId): bool;
    public function save(LoanRequest $request): void;
}
