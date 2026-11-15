<?php

declare(strict_types=1);

namespace App\ReadModel\LoanRequests\Application;

final readonly class LoanRequestsResult
{
    /**
     * @param list<array{
     *     id: string,
     *     status: string,
     *     requestedAt: string,
     *     updatedAt: string,
     *     copy: array{id: string, title: string, authors: list<string>, cover: array{source: string, previewUrl: string, largeUrl: string, reference?: string}|null, canOpenDetail: bool},
     *     otherParticipant: array{displayName: string},
     *     actions: list<string>,
     *     ownerEmail?: string
     * }> $received
     * @param list<array{
     *     id: string,
     *     status: string,
     *     requestedAt: string,
     *     updatedAt: string,
     *     copy: array{id: string, title: string, authors: list<string>, cover: array{source: string, previewUrl: string, largeUrl: string, reference?: string}|null, canOpenDetail: bool},
     *     otherParticipant: array{displayName: string},
     *     actions: list<string>,
     *     ownerEmail?: string
     * }> $sent
     */
    public function __construct(
        public array $received,
        public array $sent,
    ) {
    }
}
